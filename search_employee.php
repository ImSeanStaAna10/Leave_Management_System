<?php
session_start();
include 'db.php';

// Check if user is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

$searchTerm = isset($_GET['term']) ? trim($_GET['term']) : '';

if (empty($searchTerm)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Search term is required']);
    exit();
}

// Search for employees by name or ID
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM leaves WHERE user_id = u.id) as total_leaves,
        (SELECT COUNT(*) FROM leaves WHERE user_id = u.id AND status = 'approved') as approved_leaves,
        (SELECT COUNT(*) FROM leaves WHERE user_id = u.id AND status = 'pending') as pending_leaves,
        (SELECT COUNT(*) FROM leaves WHERE user_id = u.id AND status = 'rejected') as rejected_leaves
        FROM users u 
        WHERE u.role = 'employee' 
        AND (u.name LIKE ? OR u.employee_id LIKE ?)
        ORDER BY u.name";

$searchPattern = "%$searchTerm%";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $searchPattern, $searchPattern);
$stmt->execute();
$result = $stmt->get_result();

$employees = [];
while ($employee = $result->fetch_assoc()) {
    // Get recent leaves for this employee
    $leaves_sql = "SELECT l.*, lt.name as type 
                  FROM leaves l 
                  JOIN leave_types lt ON l.leave_type = lt.id 
                  WHERE l.user_id = ? 
                  ORDER BY l.applied_at DESC 
                  LIMIT 5";
    $leaves_stmt = $conn->prepare($leaves_sql);
    $leaves_stmt->bind_param('i', $employee['id']);
    $leaves_stmt->execute();
    $leaves_result = $leaves_stmt->get_result();
    
    $recent_leaves = [];
    while ($leave = $leaves_result->fetch_assoc()) {
        $recent_leaves[] = [
            'type' => $leave['type'],
            'start_date' => $leave['start_date'],
            'end_date' => $leave['end_date'],
            'status' => $leave['status']
        ];
    }
    
    $employee['recent_leaves'] = $recent_leaves;
    $employees[] = $employee;
}

header('Content-Type: application/json');
echo json_encode($employees);
?> 