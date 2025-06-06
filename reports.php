<?php
session_start();
include 'db.php';

// Check if user is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.html');
    exit();
}

// Get current month and year
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Get monthly leave statistics
$sql = "SELECT 
            u.name as employee_name,
            COUNT(l.id) as total_leaves,
            SUM(l.duration) as total_days,
            GROUP_CONCAT(DISTINCT lt.name) as leave_types
        FROM users u
        LEFT JOIN leaves l ON u.id = l.user_id 
            AND MONTH(l.start_date) = ? 
            AND YEAR(l.start_date) = ?
            AND l.status = 'approved'
        LEFT JOIN leave_types lt ON l.leave_type = lt.id
        WHERE u.role = 'employee'
        GROUP BY u.id, u.name
        ORDER BY total_days DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $month, $year);
$stmt->execute();
$monthly_stats = $stmt->get_result();

// Get absenteeism trends (last 6 months)
$sql = "SELECT 
            DATE_FORMAT(start_date, '%Y-%m') as month_year,
            COUNT(DISTINCT user_id) as unique_employees,
            SUM(duration) as total_days
        FROM leaves 
        WHERE status = 'approved'
        AND start_date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(start_date, '%Y-%m')
        ORDER BY month_year";

$trends = $conn->query($sql);

// Get leave type distribution
$sql = "SELECT 
            lt.name as leave_type,
            COUNT(l.id) as count,
            SUM(l.duration) as total_days
        FROM leave_types lt
        LEFT JOIN leaves l ON lt.id = l.leave_type 
            AND MONTH(l.start_date) = ? 
            AND YEAR(l.start_date) = ?
            AND l.status = 'approved'
        GROUP BY lt.id, lt.name
        ORDER BY total_days DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $month, $year);
$stmt->execute();
$leave_type_stats = $stmt->get_result();

// Calculate totals for summary cards
$total_leaves = 0;
$total_days = 0;
$unique_employees = 0;
$monthly_stats->data_seek(0);
while ($row = $monthly_stats->fetch_assoc()) {
    if ($row['total_leaves'] > 0) {
        $total_leaves += $row['total_leaves'];
        $total_days += $row['total_days'];
        $unique_employees++;
    }
}

// Get pending requests count
$pending_stmt = $conn->prepare("SELECT COUNT(*) as count FROM leaves WHERE status = 'pending'");
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();
$pending_count = $pending_result->fetch_assoc()['count'];

// Get rejection rate
$total_stmt = $conn->prepare("SELECT COUNT(*) as total FROM leaves");
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_leaves_all = $total_result->fetch_assoc()['total'];

$rejected_stmt = $conn->prepare("SELECT COUNT(*) as rejected FROM leaves WHERE status = 'rejected'");
$rejected_stmt->execute();
$rejected_result = $rejected_stmt->get_result();
$rejected_count = $rejected_result->fetch_assoc()['rejected'];

$rejection_rate = $total_leaves_all > 0 ? round(($rejected_count / $total_leaves_all) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics | LeaveManager</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --dark: #212529;
            --light: #f8f9fa;
            --sidebar-width: 250px;
            --header-height: 60px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fb;
            overflow-x: hidden;
        }
        
        /* Sidebar styles */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: linear-gradient(180deg, var(--primary), var(--secondary));
            color: white;
            padding-top: var(--header-height);
            z-index: 100;
            box-shadow: 3px 0 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 15px;
            border-radius: 8px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
        }
        
        .sidebar .nav-link:hover, 
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.15);
            text-decoration: none;
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        .sidebar .logo {
            position: absolute;
            top: 15px;
            left: 20px;
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        /* Header */
        .header {
            background: white;
            height: var(--header-height);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            position: fixed;
            top: 0;
            right: 0;
            left: var(--sidebar-width);
            z-index: 99;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            transition: all 0.3s ease;
        }
        
        /* Dashboard cards */
        .dashboard-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
            margin-bottom: 20px;
            height: 100%;
            color: white;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        
        .dashboard-card .card-body {
            padding: 20px;
        }
        
        .dashboard-card .card-title {
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        .dashboard-card .card-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .dashboard-card .card-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        
        /* Custom table styling */
        .custom-table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .custom-table thead th {
            background: var(--primary);
            color: white;
            padding: 15px 20px;
            font-weight: 600;
        }
        
        .custom-table tbody tr {
            transition: background 0.2s;
        }
        
        .custom-table tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }
        
        .custom-table tbody td {
            padding: 15px 20px;
            border-top: 1px solid #edf2f9;
        }
        
        /* Status badges */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Section titles */
        .section-title {
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
            color: var(--dark);
        }
        
        .section-title i {
            margin-right: 10px;
            color: var(--primary);
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .header {
                left: 0;
            }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* Chart containers */
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            position: relative;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .chart-title {
            font-weight: 600;
            font-size: 1.2rem;
            color: var(--dark);
        }
        
        .date-selector {
            display: flex;
            gap: 10px;
        }
        
        /* Report cards */
        .report-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .report-stat {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin: 10px 0;
        }
        
        .report-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        /* Export button */
        .export-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            transition: all 0.3s;
        }
        
        .export-btn:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        /* Action buttons */
        .btn-action {
            padding: 5px 12px;
            font-size: 0.85rem;
            border-radius: 4px;
            margin-right: 5px;
        }
        
        .btn-approve {
            background: #28a745;
            color: white;
            border: none;
        }
        
        .btn-reject {
            background: #dc3545;
            color: white;
            border: none;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">LeaveManager</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="admin_dashboard.php#dashboard">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_dashboard.php#employee-list">
                    <i class="bi bi-people"></i> Employees
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_dashboard.php#request-list">
                    <i class="bi bi-list-check"></i> Leave Requests
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_dashboard.php#leave-types">
                    <i class="bi bi-card-list"></i> Leave Types
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="calendar_view.php">
                    <i class="bi bi-calendar"></i> Calendar View
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="reports.php">
                    <i class="bi bi-bar-chart"></i> Reports & Analytics
                </a>
            </li>
            <li class="nav-item mt-4">
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-left"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Reports Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="section-title"><i class="bi bi-bar-chart"></i> Reports & Analytics</h3>
        </div>
        
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-6 col-lg-3">
                <div class="dashboard-card bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="card-title">Total Leave Days</div>
                                <div class="card-value"><?= $total_days ?></div>
                            </div>
                            <div class="card-icon">
                                <i class="bi bi-calendar-week"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="dashboard-card bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="card-title">Avg. Leave Duration</div>
                                <div class="card-value"><?= $total_leaves > 0 ? round($total_days / $total_leaves, 1) : 0 ?> days</div>
                            </div>
                            <div class="card-icon">
                                <i class="bi bi-clock"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="dashboard-card bg-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="card-title">Pending Requests</div>
                                <div class="card-value"><?= $pending_count ?></div>
                            </div>
                            <div class="card-icon">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="dashboard-card bg-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="card-title">Rejection Rate</div>
                                <div class="card-value"><?= $rejection_rate ?>%</div>
                            </div>
                            <div class="card-icon">
                                <i class="bi bi-x-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8">
                <!-- Leave Trends Chart -->
                <div class="chart-container">
                    <div class="chart-header">
                        <div class="chart-title">Leave Trends (Last 6 Months)</div>
                        <button class="export-btn" onclick="exportReport('trends')">
                            <i class="bi bi-download me-1"></i> Export
                        </button>
                    </div>
                    <canvas id="trendChart" height="300"></canvas>
                </div>
                
                <!-- Department Comparison Chart -->
                <div class="chart-container">
                    <div class="chart-header">
                        <div class="chart-title">Leave Days by Department</div>
                        <div class="date-selector">
                            <select class="form-select form-select-sm" id="yearFilter">
                                <option value="2023">2023</option>
                                <option value="2022">2022</option>
                                <option value="2021">2021</option>
                            </select>
                        </div>
                    </div>
                    <canvas id="departmentChart" height="300"></canvas>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Leave Type Distribution -->
                <div class="chart-container">
                    <div class="chart-header">
                        <div class="chart-title">Leave Type Distribution</div>
                        <button class="export-btn" onclick="exportReport('types')">
                            <i class="bi bi-download me-1"></i> Export
                        </button>
                    </div>
                    <canvas id="leaveTypeChart" height="300"></canvas>
                </div>
                
                <!-- Top Employees on Leave -->
                <div class="report-card">
                    <h5 class="mb-4"><i class="bi bi-trophy me-2"></i> Top 5 Employees on Leave</h5>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Days</th>
                                <th>Dept</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Reset pointer and get top 5 employees
                            $monthly_stats->data_seek(0);
                            $counter = 0;
                            while($counter < 5 && $row = $monthly_stats->fetch_assoc()):
                                if ($row['total_days'] > 0): 
                                    $counter++;
                                    // Get department for this employee
                                    $dept_stmt = $conn->prepare("SELECT department FROM users WHERE name = ?");
                                    $dept_stmt->bind_param("s", $row['employee_name']);
                                    $dept_stmt->execute();
                                    $dept_result = $dept_stmt->get_result();
                                    $department = $dept_result->fetch_assoc()['department'];
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                <td><?= $row['total_days'] ?></td>
                                <td><?= htmlspecialchars($department) ?></td>
                            </tr>
                            <?php 
                                endif;
                            endwhile; 
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Detailed Reports -->
        <div class="chart-container mt-4">
            <div class="chart-header">
                <div class="chart-title">Monthly Leave Report</div>
                <div class="d-flex gap-2">
                    <div class="date-selector">
                        <select class="form-select form-select-sm" id="monthSelector" onchange="updateMonthlyReport()">
                            <?php
                            for ($m = 1; $m <= 12; $m++) {
                                $monthName = date('F', mktime(0, 0, 0, $m, 1));
                                $selected = ($m == $month) ? 'selected' : '';
                                echo "<option value='$m' $selected>$monthName</option>";
                            }
                            ?>
                        </select>
                        <select class="form-select form-select-sm" id="yearSelector" onchange="updateMonthlyReport()">
                            <?php
                            $currentYear = date('Y');
                            for ($y = $currentYear - 1; $y <= $currentYear + 1; $y++) {
                                $selected = ($y == $year) ? 'selected' : '';
                                echo "<option value='$y' $selected>$y</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button class="export-btn" onclick="exportReport('monthly')">
                        <i class="bi bi-download me-1"></i> Export
                    </button>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Leave Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Duration</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Get detailed monthly report data
                        $detail_sql = "SELECT 
                                            u.name as employee_name,
                                            u.department,
                                            lt.name as leave_type,
                                            l.start_date,
                                            l.end_date,
                                            l.duration,
                                            l.status
                                        FROM leaves l
                                        JOIN users u ON l.user_id = u.id
                                        JOIN leave_types lt ON l.leave_type = lt.id
                                        WHERE MONTH(l.start_date) = ?
                                        AND YEAR(l.start_date) = ?
                                        AND l.status = 'approved'
                                        ORDER BY l.start_date DESC";
                        
                        $detail_stmt = $conn->prepare($detail_sql);
                        $detail_stmt->bind_param("ii", $month, $year);
                        $detail_stmt->execute();
                        $detail_result = $detail_stmt->get_result();
                        
                        while($row = $detail_result->fetch_assoc()):
                            $status_class = $row['status'] === 'approved' ? 'badge-approved' : 
                                          ($row['status'] === 'pending' ? 'badge-pending' : 'badge-rejected');
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['employee_name']) ?></td>
                            <td><?= htmlspecialchars($row['department']) ?></td>
                            <td><?= htmlspecialchars($row['leave_type']) ?></td>
                            <td><?= date('d-m-Y', strtotime($row['start_date'])) ?></td>
                            <td><?= date('d-m-Y', strtotime($row['end_date'])) ?></td>
                            <td><?= $row['duration'] ?> days</td>
                            <td><span class="status-badge <?= $status_class ?>"><?= ucfirst($row['status']) ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Leave Type Distribution Chart
            const typeCtx = document.getElementById('leaveTypeChart').getContext('2d');
            new Chart(typeCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php 
                        $labels = [];
                        $data = [];
                        $leave_type_stats->data_seek(0);
                        while ($row = $leave_type_stats->fetch_assoc()) {
                            $labels[] = $row['leave_type'];
                            $data[] = $row['total_days'];
                        }
                        echo json_encode($labels);
                    ?>,
                    datasets: [{
                        data: <?php 
                            $leave_type_stats->data_seek(0);
                            $data = [];
                            while ($row = $leave_type_stats->fetch_assoc()) {
                                $data[] = $row['total_days'];
                            }
                            echo json_encode($data);
                        ?>,
                        backgroundColor: [
                            '#4361ee',
                            '#4cc9f0',
                            '#3f37c9',
                            '#4895ef',
                            '#560bad',
                            '#7209b7',
                            '#b5179e'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
            
            // Absenteeism Trends Chart
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: <?php 
                        $labels = [];
                        $data = [];
                        $trends->data_seek(0);
                        while ($row = $trends->fetch_assoc()) {
                            $labels[] = date('M Y', strtotime($row['month_year'] . '-01'));
                            $data[] = $row['total_days'];
                        }
                        echo json_encode($labels);
                    ?>,
                    datasets: [{
                        label: 'Total Leave Days',
                        data: <?php 
                            $trends->data_seek(0);
                            $data = [];
                            while ($row = $trends->fetch_assoc()) {
                                $data[] = $row['total_days'];
                            }
                            echo json_encode($data);
                        ?>,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Total Days'
                            }
                        }
                    }
                }
            });
            
            // Department Comparison Chart (static for demo - would be replaced with real data)
            const deptCtx = document.getElementById('departmentChart').getContext('2d');
            new Chart(deptCtx, {
                type: 'bar',
                data: {
                    labels: ['IT', 'Marketing', 'HR', 'Finance', 'Operations'],
                    datasets: [{
                        label: 'Total Leave Days',
                        data: [84, 92, 78, 65, 87],
                        backgroundColor: '#4361ee',
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Total Days'
                            }
                        }
                    }
                }
            });
        });
        
        // Update monthly report when date changes
        function updateMonthlyReport() {
            const month = document.getElementById('monthSelector').value;
            const year = document.getElementById('yearSelector').value;
            window.location.href = `reports.php?month=${month}&year=${year}`;
        }
        
        // Export functions
        function exportReport(type) {
            const month = document.getElementById('monthSelector').value;
            const year = document.getElementById('yearSelector').value;
            alert(`Exporting ${type} report for ${month}/${year}...`);
            // In a real application, this would redirect to an export script
        }
        
        function exportAllReports() {
            alert('Exporting all reports...');
            // In a real application, this would redirect to an export script
        }
    </script>
</body>
</html>