<?php
session_start();
include 'db.php';

// Check if user is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.html');
    exit();
}

// Get all approved leaves
$sql = 'SELECT l.*, u.name as employee_name, u.profile_picture, lt.name as leave_type 
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
            display: flex;
            align-items: center;
        }
        
        .name-item:hover {
            background: #e3f2fd;
            transform: translateX(5px);
        }
        
        .name-item img.profile-pic-modal {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            border: 2px solid var(--primary);
        }
        
        .name-item .employee-info {
            flex-grow: 1;
        }
        
        .name-item .employee-info strong {
            display: block;
            font-size: 1.1em;
            color: var(--dark);
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
        <div class="search-container mb-3 px-3">
            <div class="input-group">
                <input type="text" id="globalSearch" class="form-control" placeholder="Search employee...">
                <button class="btn btn-light" type="button" onclick="performSearch()">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </div>
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
    
    <!-- Search Results Modal -->
    <div class="modal fade" id="searchResultsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Employee Search Results</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="searchResultsContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printSearchResults()">
                        <i class="bi bi-printer me-1"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    
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
                    
                    // Add profile picture
                    const profilePicUrl = leave.profile_picture && leave.profile_picture !== 'default-avatar.png' ? leave.profile_picture : 'uploads/default-avatar.png';
                    nameItem.innerHTML = `
                        <img src="${profilePicUrl}" alt="Profile Picture" class="profile-pic-modal">
                        <div class="employee-info">
                            <strong>${leave.employee_name}</strong>
                            <small>${leave.leave_type}</small>
                        </div>
                    `;
                    
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
            // Clear names list
            document.getElementById('namesList').innerHTML = '';
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
            document.getElementById('overlay').style.display = 'block';
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

        // Initialize Bootstrap modals
        const searchModal = new bootstrap.Modal(document.getElementById('searchResultsModal'));

        function performSearch() {
            const searchTerm = document.getElementById('globalSearch').value.trim();
            if (!searchTerm) return;

            // Show loading state
            const searchResultsContent = document.getElementById('searchResultsContent');
            searchResultsContent.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';

            // Fetch search results
            fetch(`search_employee.php?term=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        searchResultsContent.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        return;
                    }

                    let html = '';
                    data.forEach(employee => {
                        html += `
                            <div class="card mb-3 shadow-sm border-0">
                                <div class="card-header bg-white py-3 border-bottom-0">
                                    <div class="d-flex align-items-center">
                                            <img src="${employee.profile_picture && employee.profile_picture !== 'default-avatar.png' ? employee.profile_picture : 'uploads/default-avatar.png'}" 
                                             alt="Profile Picture" class="me-3 shadow-sm" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;">
                                        <div>
                                            <h4 class="mb-0 text-primary">${employee.name} <small class="text-muted">(${employee.employee_id})</small></h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body pt-0">
                                    <div class="row mb-3">
                                                <div class="col-md-6">
                                            <h6 class="text-secondary mb-2">Employee Information</h6>
                                            <p class="mb-1"><strong>Department:</strong> ${employee.department}</p>
                                            <p class="mb-1"><strong>Job Title:</strong> ${employee.job_title}</p>
                                            <p class="mb-1"><strong>Email:</strong> ${employee.email}</p>
                                            <p class="mb-1"><strong>Contact:</strong> ${employee.contact_number}</p>
                                                </div>
                                                <div class="col-md-6">
                                            <h6 class="text-secondary mb-2">Leave Statistics</h6>
                                            <p class="mb-1"><strong>Total Leaves:</strong> ${employee.total_leaves}</p>
                                            <p class="mb-1"><strong>Approved:</strong> ${employee.approved_leaves}</p>
                                            <p class="mb-1"><strong>Pending:</strong> ${employee.pending_leaves}</p>
                                            <p class="mb-1"><strong>Rejected:</strong> ${employee.rejected_leaves}</p>
                                                </div>
                                            </div>
                                    <hr class="my-3">
                                    <div>
                                        <h6 class="text-secondary mb-2">Recent Leave History</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover mb-0">
                                                <thead>
                                                    <tr class="table-light">
                                                        <th>Type</th>
                                                        <th>Start Date</th>
                                                        <th>End Date</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${employee.recent_leaves.map(leave => `
                                                        <tr>
                                                            <td>${leave.type}</td>
                                                            <td>${leave.start_date}</td>
                                                            <td>${leave.end_date}</td>
                                                            <td><span class="badge bg-${leave.status === 'approved' ? 'success' : leave.status === 'pending' ? 'warning' : 'danger'}">${leave.status}</span></td>
                                                        </tr>
                                                    `).join('')}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    searchResultsContent.innerHTML = html || '<div class="alert alert-info">No results found</div>';
                })
                .catch(error => {
                    searchResultsContent.innerHTML = '<div class="alert alert-danger">Error performing search</div>';
                    console.error('Search error:', error);
                });

            // Show the modal
            searchModal.show();
        }

        // Handle Enter key in search input
        document.getElementById('globalSearch').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });

        function printSearchResults() {
            const content = document.getElementById('searchResultsContent').innerHTML;
            const printWindow = window.open('', '_blank');

            let printContentHtml = '';
            document.querySelectorAll('#searchResultsContent .card').forEach(card => {
                const nameHeader = card.querySelector('.card-header h4').textContent;
                const profilePicUrl = card.querySelector('.card-header img') ? card.querySelector('.card-header img').src : 'uploads/default-avatar.png';
                const employeeInfoDiv = card.querySelector('.card-body > .row > div:nth-child(1)');
                const leaveStatsDiv = card.querySelector('.card-body > .row > div:nth-child(2)');
                const leaveHistoryDiv = card.querySelector('.card-body > div:nth-child(3)');

                let employeeInfoHtml = '';
                if (employeeInfoDiv) {
                    employeeInfoHtml += employeeInfoDiv.innerHTML.replace('<h6 class="text-secondary mb-2">Employee Information</h6>', '');
                }

                let leaveStatsHtml = '';
                if (leaveStatsDiv) {
                    leaveStatsHtml += leaveStatsDiv.innerHTML.replace('<h6 class="text-secondary mb-2">Leave Statistics</h6>', '');
                }

                let leaveHistoryHtml = '';
                if (leaveHistoryDiv) {
                    leaveHistoryHtml += leaveHistoryDiv.innerHTML.replace('<h6 class="text-secondary mb-2">Recent Leave History</h6>', '');
                 }

                printContentHtml += `
                    <div class="print-employee-section mb-4">
                        <div class="d-flex align-items-center mb-3">
                            <img src="${profilePicUrl}" alt="Profile Picture" class="print-profile-pic me-3">
                            <div>
                                <h4 class="mb-1">${nameHeader}</h4>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-6 print-info-block">
                                <h6>Employee Information</h6>
                                ${employeeInfoHtml}
                            </div>
                            <div class="col-6 print-info-block">
                                <h6>Leave Statistics</h6>
                                ${leaveStatsHtml}
                            </div>
                        </div>

                        <div class="mt-3 print-history-block">
                            <h6>Recent Leave History</h6>
                            ${leaveHistoryHtml}
                        </div>
                    </div>
                    <div class="print-page-break"></div>
                `;
            });

            printWindow.document.write(`
                <html>
                    <head>
                        <title>Employee Search Results Report</title>
                        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
                        <style>
                            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 30px; color: #333; }
                            .print-header { text-align: center; margin-bottom: 40px; border-bottom: 2px solid #000; padding-bottom: 15px; }
                            .print-header h1 { margin: 0; color: #333; }
                            .print-employee-section { margin-bottom: 30px; padding: 20px; border: 1px solid #000; border-radius: 8px; background-color: #fff; }
                            .print-profile-pic { width: 100px; height: 100px; object-fit: cover; border: 1px solid #000; border-radius: 8px; }
                            .print-info-block, .print-history-block { padding: 15px; border: 1px solid #eee; border-radius: 5px; margin-bottom: 15px; }
                            .print-history-block table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                            .print-history-block th, .print-history-block td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                            .print-history-block th { background-color: #f2f2f2; }
                            h4, h5 { color: #555; margin-top: 0; margin-bottom: 10px; }
                            p { margin-bottom: 5px; font-size: 0.95rem; line-height: 1.4; }
                            .status-badge { padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; }
                            .badge-approved { background-color: #d4edda; color: #155724; }
                            .badge-pending { background-color: #fff3cd; color: #856404; }
                            .badge-rejected { background-color: #f8d7da; color: #721c24; }
                            .print-page-break { page-break-after: always; }
                            .print-page-break:last-child { page-break-after: avoid; }
                            @media print {
                                .no-print { display: none; }
                                body { padding: 0; margin: 0; }
                                .print-employee-section { box-shadow: none; border: 1px solid #000; }
                                .print-info-block, .print-history-block { border: 1px solid #000; }
                                .print-history-block table, .print-history-block th, .print-history-block td { border: 1px solid #000; }
                                .print-header { border-bottom-color: #000; }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="no-print mb-3">
                            <button onclick="window.print()" class="btn btn-primary">Print</button>
                            <button onclick="window.close()" class="btn btn-secondary">Close</button>
                        </div>

                        <div class="print-header">
                            <h1>Employee Search Results</h1>
                        </div>

                        ${printContentHtml}
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
    </script>
</body>
</html>