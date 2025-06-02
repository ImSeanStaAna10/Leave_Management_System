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
<html>
<head>
    <title>Leave Calendar</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .dashboard-container {
            display: flex;
            height: 100vh; /* Full viewport height */
            width: 100%;
        }

        .sidebar {
            width: 200px; /* Fixed width */
            background-color: #f0f0f0; /* Light grey background */
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1); /* Subtle shadow on the right */
            display: flex;
            flex-direction: column;
            gap: 10px;
            border-right: 1px solid black; /* Added black border on the right */
        }

        .sidebar a {
            display: block;
            padding: 10px;
            background-color: #fff; /* White background for links */
            border: 1px solid #ddd; /* Added border */
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            text-align: center;
            transition: background-color 0.2s ease, border-color 0.2s ease; /* Added border-color to transition */
        }

        .sidebar a:hover {
            background-color: #eee; /* Slightly darker on hover */
            border-color: #ccc; /* Darker border on hover */
        }

        .sidebar a.active {
            background-color: #111; /* Black background for active */
            color: #fff; /* White text for active */
            border-color: #111; /* Dark border for active */
        }

        .main-content {
            flex: 1; /* Take remaining width */
            padding: 20px;
            overflow-y: auto; /* Add scrolling if content exceeds height */
            width: calc(100% - 200px); /* Adjust width based on sidebar */
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

        .calendar-container {
            max-width: 1200px;
            margin: 20px auto;
            background: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
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
            font-size: 2em;
            font-weight: 700;
            color: #2c3e50;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .calendar-nav {
            display: flex;
            gap: 12px;
        }

        .calendar-nav a {
            padding: 10px 20px;
            background: #2c3e50;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 0.95em;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .calendar-nav a:hover {
            background: #34495e;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .calendar {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin-top: 20px;
        }

        .calendar th {
            background: #3498db;
            color: #fff;
            padding: 15px;
            text-align: center;
            font-weight: 600;
            border-radius: 8px;
            font-size: 1.1em;
            text-transform: uppercase;
            letter-spacing: 1px;
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

        .calendar td > div:first-child {
            font-size: 1.2em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f0f0f0;
        }

        .leave-event {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 8px 12px;
            margin: 4px 0;
            border-radius: 4px;
            font-size: 0.9em;
            color: #1976d2;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            cursor: pointer;
        }

        .leave-event:hover {
            background: #bbdefb;
            transform: translateX(2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.15);
        }

        .leave-details {
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

        .leave-details h3 {
            margin: 0 0 20px 0;
            color: #2c3e50;
            font-size: 1.5em;
            font-weight: 700;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .leave-details p {
            margin: 12px 0;
            color: #34495e;
            font-size: 1.1em;
            display: flex;
            align-items: center;
        }

        .leave-details p strong {
            min-width: 120px;
            color: #7f8c8d;
        }

        .leave-details .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #95a5a6;
            transition: color 0.2s;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .leave-details .close-btn:hover {
            color: #e74c3c;
            background: #f8f9fa;
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

        /* Names Modal */
        .names-modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            z-index: 1000;
            max-width: 400px;
            width: 90%;
        }

        .names-modal h3 {
            margin: 0 0 20px 0;
            color: #2c3e50;
            font-size: 1.3em;
            font-weight: 700;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .names-list {
            max-height: 300px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .names-list::-webkit-scrollbar {
            width: 8px;
        }

        .names-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .names-list::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .names-list::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .name-item {
            padding: 10px 15px;
            margin: 5px 0;
            background: #f8f9fa;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .name-item:hover {
            background: #e3f2fd;
            transform: translateX(5px);
        }

        .names-modal .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #95a5a6;
            transition: color 0.2s;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .names-modal .close-btn:hover {
            color: #e74c3c;
            background: #f8f9fa;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .calendar-container {
                padding: 15px;
                margin: 10px;
            }

            .calendar-title {
                font-size: 1.5em;
            }

            .calendar-nav {
                flex-direction: column;
            }

            .calendar th {
                padding: 10px;
                font-size: 0.9em;
            }

            .calendar td {
                height: 100px;
                padding: 10px;
            }

            .leave-event {
                font-size: 0.8em;
                padding: 6px 8px;
            }
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
            <a href="calendar_view.php" class="active">Calendar View</a>
            <a href="reports.php">Reports & Analytics</a>
        </div>

        <div class="main-content">
            <div style="text-align:right; margin-bottom: 20px;">
                <a href="logout.php" class="logout-link">Logout</a>
            </div>

            <div class="calendar-container">
                <div class="calendar-header">
                    <div class="calendar-title"><?= $monthName . ' ' . $year ?></div>
                    <div class="calendar-nav">
                        <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>">Previous</a>
                        <a href="?month=<?= date('m') ?>&year=<?= date('Y') ?>">Current</a>
                        <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>">Next</a>
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
                                    echo "<div style='font-weight:bold; margin-bottom:5px;'>" . $currentDay . "</div>";
                                    
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
    </div>

    <!-- Names Modal -->
    <div class="names-modal" id="namesModal">
        <button class="close-btn" onclick="hideNamesModal()">&times;</button>
        <h3>Employees on Leave</h3>
        <div class="names-list" id="namesList"></div>
    </div>

    <div class="overlay" id="overlay"></div>
    <div class="leave-details" id="leaveDetails">
        <button class="close-btn" onclick="hideLeaveDetails()">&times;</button>
        <h3>Leave Details</h3>
        <p><strong>Employee:</strong> <span id="employeeName"></span></p>
        <p><strong>Leave Type:</strong> <span id="leaveType"></span></p>
        <p><strong>Start Date:</strong> <span id="startDate"></span></p>
        <p><strong>End Date:</strong> <span id="endDate"></span></p>
        <p><strong>Duration:</strong> <span id="duration"></span> days</p>
        <p><strong>Purpose:</strong> <span id="purpose"></span></p>
    </div>

    <script>
        // Store all leaves data
        const leavesData = <?php 
            $result->data_seek(0);
            $leaves = [];
            while ($leave = $result->fetch_assoc()) {
                $leaves[] = $leave;
            }
            echo json_encode($leaves);
        ?>;

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

        function hideNamesModal() {
            document.getElementById('namesModal').style.display = 'none';
            document.getElementById('overlay').style.display = 'none';
        }

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