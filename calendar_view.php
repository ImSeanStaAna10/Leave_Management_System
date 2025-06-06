<?php
session_start();
include 'db.php';

// Check if user is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.html');
    exit();
}

// Get all approved leaves
$sql = 'SELECT l.*, u.name as employee_name, lt.name as leave_type 
        FROM leaves l 
        JOIN users u ON l.user_id = u.id 
        JOIN leave_types lt ON l.leave_type = lt.id 
        WHERE l.status = "approved" 
        ORDER BY l.start_date';
$result = $conn->query($sql);

// Get current month and year
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Get first day of the month
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$numberDays = date('t', $firstDay);
$dateComponents = getdate($firstDay);
$monthName = $dateComponents['month'];
$dayOfWeek = $dateComponents['wday'];

// Previous and next month links
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Calendar</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
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
        
        /* Calendar styles */
        .calendar-container {
            margin-top: 80px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 25px;
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .calendar-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .calendar-nav {
            display: flex;
            gap: 10px;
        }
        
        .calendar-nav .btn {
            padding: 8px 20px;
            font-weight: 600;
        }
        
        .calendar {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin-top: 20px;
        }
        
        .calendar th {
            background: var(--primary);
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: 600;
            border-radius: 8px;
            font-size: 1.1em;
        }
        
        .calendar td {
            border: none;
            padding: 15px;
            height: 120px;
            vertical-align: top;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }
        
        .calendar td:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .calendar td.other-month {
            background: #f8f9fa;
            color: #adb5bd;
            cursor: default;
        }
        
        .calendar td.today {
            background: #fff3e0;
            border: 2px solid #ff9800;
        }
        
        .calendar-day {
            position: absolute;
            top: 10px;
            right: 10px;
            font-weight: 600;
            font-size: 1.2rem;
            color: var(--dark);
        }
        
        .leave-count {
            position: absolute;
            bottom: 10px;
            left: 10px;
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        /* Modal styles */
        .names-modal, .leave-details {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            z-index: 1000;
            max-width: 450px;
            width: 90%;
        }
        
        .modal-header {
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 20px;
        }
        
        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #95a5a6;
            transition: color 0.2s;
        }
        
        .close-btn:hover {
            color: #e74c3c;
        }
        
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(3px);
            z-index: 999;
        }
        
        .names-list {
            max-height: 300px;
            overflow-y: auto;
            padding-right: 10px;
        }
        
        .name-item {
            padding: 12px 15px;
            margin: 8px 0;
            background: #f8f9fa;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .name-item:hover {
            background: #e3f2fd;
            transform: translateX(5px);
        }
        
        .leave-details p {
            margin: 15px 0;
            font-size: 1.1rem;
        }
        
        .leave-details p strong {
            display: inline-block;
            min-width: 120px;
            color: #6c757d;
        }
        
        /* Logout button */
        .logout-link {
            padding: 8px 20px;
            background: #e74c3c;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .logout-link:hover {
            background: #c0392b;
            transform: translateY(-2px);
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
        
        @media (max-width: 768px) {
            .calendar td {
                height: 100px;
                padding: 10px;
            }
            
            .calendar-day {
                font-size: 1rem;
            }
            
            .leave-count {
                font-size: 0.75rem;
            }
            
            .calendar-title {
                font-size: 1.5rem;
            }
            
            .calendar-nav {
                flex-wrap: wrap;
                justify-content: center;
            }
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
                <a class="nav-link active" href="calendar_view.php">
                    <i class="bi bi-calendar"></i> Calendar View
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reports.php">
                    <i class="bi bi-bar-chart"></i> Reports
                </a>
            </li>
            <li class="nav-item mt-4">
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-left"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <!-- Header -->
    <div class="header">
        <button class="btn d-lg-none" type="button" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
        <div class="d-flex align-items-center">
            <div class="me-3">
                <i class="bi bi-person-circle fs-4"></i>
            </div>
            <div>
                <div class="fw-bold">Admin</div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="calendar-container">
            <div class="calendar-header">
                <h2 class="calendar-title"><?= $monthName . ' ' . $year ?></h2>
                <div class="calendar-nav">
                    <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn btn-primary">
                        <i class="bi bi-chevron-left me-1"></i> Previous
                    </a>
                    <a href="?month=<?= date('m') ?>&year=<?= date('Y') ?>" class="btn btn-outline-secondary">
                        Current Month
                    </a>
                    <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn btn-primary">
                        Next <i class="bi bi-chevron-right ms-1"></i>
                    </a>
                </div>
            </div>
            
            <table class="calendar">
                <thead>
                    <tr>
                        <th>Sunday</th>
                        <th>Monday</th>
                        <th>Tuesday</th>
                        <th>Wednesday</th>
                        <th>Thursday</th>
                        <th>Friday</th>
                        <th>Saturday</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $currentDay = 1;
                    $currentDate = date('Y-m-d');
                    
                    // Create the calendar
                    for ($i = 0; $i < 6; $i++) {
                        echo "<tr>";
                        
                        for ($j = 0; $j < 7; $j++) {
                            if (($i === 0 && $j < $dayOfWeek) || ($currentDay > $numberDays)) {
                                // Previous or next month days
                                echo "<td class='other-month'></td>";
                            } else {
                                $date = sprintf('%04d-%02d-%02d', $year, $month, $currentDay);
                                $isToday = ($date === $currentDate);
                                
                                echo "<td" . ($isToday ? " class='today'" : "") . " onclick='showNamesForDate(\"$date\")'>";
                                echo "<div class='calendar-day'>" . $currentDay . "</div>";
                                
                                // Store leaves for this day in a data attribute
                                $leavesForDay = [];
                                $result->data_seek(0);
                                while ($leave = $result->fetch_assoc()) {
                                    $startDate = new DateTime($leave['start_date']);
                                    $endDate = new DateTime($leave['end_date']);
                                    $currentDateObj = new DateTime($date);
                                    
                                    if ($currentDateObj >= $startDate && $currentDateObj <= $endDate) {
                                        $leavesForDay[] = $leave;
                                    }
                                }
                                
                                if (!empty($leavesForDay)) {
                                    echo "<div class='leave-count'>" . count($leavesForDay) . " on leave</div>";
                                }
                                
                                echo "</td>";
                                $currentDay++;
                            }
                        }
                        
                        echo "</tr>";
                        
                        if ($currentDay > $numberDays) {
                            break;
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Names Modal -->
    <div class="names-modal" id="namesModal">
        <div class="modal-header">
            <h3 class="modal-title">Employees on Leave</h3>
            <button class="close-btn" onclick="hideNamesModal()">&times;</button>
        </div>
        <div class="names-list" id="namesList"></div>
    </div>

    <!-- Leave Details Modal -->
    <div class="leave-details" id="leaveDetails">
        <div class="modal-header">
            <h3 class="modal-title">Leave Details</h3>
            <button class="close-btn" onclick="hideLeaveDetails()">&times;</button>
        </div>
        <p><strong>Employee:</strong> <span id="employeeName"></span></p>
        <p><strong>Leave Type:</strong> <span id="leaveType"></span></p>
        <p><strong>Start Date:</strong> <span id="startDate"></span></p>
        <p><strong>End Date:</strong> <span id="endDate"></span></p>
        <p><strong>Duration:</strong> <span id="duration"></span> days</p>
        <p><strong>Purpose:</strong> <span id="purpose"></span></p>
    </div>

    <div class="overlay" id="overlay"></div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
        
        // Store all leaves data
        const leavesData = <?php 
            $result->data_seek(0);
            $leaves = [];
            while ($leave = $result->fetch_assoc()) {
                $leaves[] = $leave;
            }
            echo json_encode($leaves);
        ?>;
        
        // Function to show employees on leave for a specific date
        function showNamesForDate(date) {
            const leavesForDate = leavesData.filter(leave => {
                const startDate = new Date(leave.start_date);
                const endDate = new Date(leave.end_date);
                const currentDate = new Date(date);
                return currentDate >= startDate && currentDate <= endDate;
            });
        
            if (leavesForDate.length > 0) {
                const namesList = document.getElementById('namesList');
                namesList.innerHTML = '';
                
                leavesForDate.forEach(leave => {
                    const nameItem = document.createElement('div');
                    nameItem.className = 'name-item';
                    nameItem.textContent = leave.employee_name;
                    nameItem.onclick = () => showLeaveDetails(leave);
                    namesList.appendChild(nameItem);
                });
        
                document.getElementById('namesModal').style.display = 'block';
                document.getElementById('overlay').style.display = 'block';
            }
        }
        
        // Function to hide the names modal
        function hideNamesModal() {
            document.getElementById('namesModal').style.display = 'none';
            document.getElementById('overlay').style.display = 'none';
        }
        
        // Function to show leave details
        function showLeaveDetails(leave) {
            document.getElementById('employeeName').textContent = leave.employee_name;
            document.getElementById('leaveType').textContent = leave.leave_type;
            document.getElementById('startDate').textContent = leave.start_date;
            document.getElementById('endDate').textContent = leave.end_date;
            document.getElementById('duration').textContent = leave.duration;
            document.getElementById('purpose').textContent = leave.purpose;
            
            document.getElementById('leaveDetails').style.display = 'block';
            document.getElementById('namesModal').style.display = 'none';
        }
        
        // Function to hide leave details
        function hideLeaveDetails() {
            document.getElementById('leaveDetails').style.display = 'none';
            document.getElementById('overlay').style.display = 'none';
        }
        
        // Close modals when clicking outside
        document.getElementById('overlay').addEventListener('click', function() {
            hideNamesModal();
            hideLeaveDetails();
        });
    </script>
</body>
</html>