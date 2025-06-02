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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reports & Analytics</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .dashboard-container {
            display: flex;
            height: 100vh;
            width: 100%;
        }

        .sidebar {
            width: 200px;
            background-color: #f0f0f0;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            gap: 10px;
            border-right: 1px solid black;
        }

        .sidebar a {
            display: block;
            padding: 10px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            text-align: center;
            transition: background-color 0.2s ease, border-color 0.2s ease;
        }

        .sidebar a:hover {
            background-color: #eee;
            border-color: #ccc;
        }

        .sidebar a.active {
            background-color: #111;
            color: #fff;
            border-color: #111;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            width: calc(100% - 200px);
        }

        .report-section {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .report-title {
            font-size: 1.5em;
            color: #2c3e50;
            margin: 0;
        }

        .date-selector {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .date-selector select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .export-btn {
            padding: 8px 15px;
            background: #2ecc71;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .export-btn:hover {
            background: #27ae60;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 0.9em;
        }

        .stat-card .value {
            font-size: 2em;
            color: #2c3e50;
            font-weight: bold;
        }

        .chart-container {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .logout-link {
            display: inline-block;
            padding: 10px 20px;
            background: #e74c3c;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .logout-link:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <a href="admin_dashboard.php#dashboard">Dashboard</a>
            <a href="admin_dashboard.php#employee-list">Employee List</a>
            <a href="admin_dashboard.php#request-list">Leave Requests</a>
            <a href="admin_dashboard.php#leave-types">Leave Types</a>
            <a href="calendar_view.php">Calendar View</a>
            <a href="reports.php" class="active">Reports & Analytics</a>
        </div>

        <div class="main-content">
            <div style="text-align:right; margin-bottom: 20px;">
                <a href="logout.php" class="logout-link">Logout</a>
            </div>

            <!-- Monthly Overview -->
            <div class="report-section">
                <div class="report-header">
                    <h2 class="report-title">Monthly Leave Report</h2>
                    <div class="date-selector">
                        <select id="month" onchange="updateReports()">
                            <?php
                            for ($m = 1; $m <= 12; $m++) {
                                $selected = ($m == $month) ? 'selected' : '';
                                echo "<option value='$m' $selected>" . date('F', mktime(0, 0, 0, $m, 1)) . "</option>";
                            }
                            ?>
                        </select>
                        <select id="year" onchange="updateReports()">
                            <?php
                            $currentYear = date('Y');
                            for ($y = $currentYear - 2; $y <= $currentYear + 1; $y++) {
                                $selected = ($y == $year) ? 'selected' : '';
                                echo "<option value='$y' $selected>$y</option>";
                            }
                            ?>
                        </select>
                        <button class="export-btn" onclick="exportReport('monthly')">Export</button>
                    </div>
                </div>

                <div class="stats-grid">
                    <?php
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
                    ?>
                    <div class="stat-card">
                        <h3>Total Leave Requests</h3>
                        <div class="value"><?= $total_leaves ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Total Leave Days</h3>
                        <div class="value"><?= $total_days ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Employees on Leave</h3>
                        <div class="value"><?= $unique_employees ?></div>
                    </div>
                </div>

                <div class="chart-container">
                    <canvas id="leaveTypeChart"></canvas>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Total Leaves</th>
                            <th>Total Days</th>
                            <th>Leave Types</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $monthly_stats->data_seek(0);
                        while ($row = $monthly_stats->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>{$row['employee_name']}</td>";
                            echo "<td>{$row['total_leaves']}</td>";
                            echo "<td>{$row['total_days']}</td>";
                            echo "<td>{$row['leave_types']}</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Absenteeism Trends -->
            <div class="report-section">
                <div class="report-header">
                    <h2 class="report-title">Absenteeism Trends</h2>
                    <button class="export-btn" onclick="exportReport('trends')">Export</button>
                </div>
                <div class="chart-container">
                    <canvas id="trendsChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Leave Type Distribution Chart
        const leaveTypeData = {
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
                    '#3498db',
                    '#2ecc71',
                    '#e74c3c',
                    '#f1c40f',
                    '#9b59b6',
                    '#1abc9c'
                ]
            }]
        };

        new Chart(document.getElementById('leaveTypeChart'), {
            type: 'pie',
            data: leaveTypeData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right'
                    },
                    title: {
                        display: true,
                        text: 'Leave Type Distribution'
                    }
                }
            }
        });

        // Absenteeism Trends Chart
        const trendsData = {
            labels: <?php 
                $labels = [];
                $trends->data_seek(0);
                while ($row = $trends->fetch_assoc()) {
                    $labels[] = date('M Y', strtotime($row['month_year'] . '-01'));
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
                fill: true
            }]
        };

        new Chart(document.getElementById('trendsChart'), {
            type: 'line',
            data: trendsData,
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Leave Trends (Last 6 Months)'
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

        function updateReports() {
            const month = document.getElementById('month').value;
            const year = document.getElementById('year').value;
            window.location.href = `reports.php?month=${month}&year=${year}`;
        }

        function exportReport(type) {
            const month = document.getElementById('month').value;
            const year = document.getElementById('year').value;
            window.location.href = `export_report.php?type=${type}&month=${month}&year=${year}`;
        }
    </script>
</body>
</html> 