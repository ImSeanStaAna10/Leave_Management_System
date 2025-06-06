<?php
session_start();
include 'db.php';

// Check if user is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.html');
    exit();
}

$searchTerm = isset($_GET['term']) ? trim($_GET['term']) : '';

if (empty($searchTerm)) {
    die('Search term is required');
}

// Search for employees by name or ID
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM leaves WHERE user_id = u.id) as total_leaves,
        (SELECT COUNT(*) FROM leaves WHERE user_id = u.id AND status = 'approved') as approved_leaves,
        (SELECT COUNT(*) FROM user_id = u.id AND status = 'pending') as pending_leaves,
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

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="employee_search_results_' . date('Y-m-d') . '.csv"');

// Create CSV file
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add headers
fputcsv($output, [
    'Employee ID',
    'Name',
    'Department',
    'Job Title',
    'Email',
    'Contact Number',
    'Total Leaves',
    'Approved Leaves',
    'Pending Leaves',
    'Rejected Leaves'
]);

// Add data
while ($employee = $result->fetch_assoc()) {
    fputcsv($output, [
        $employee['employee_id'],
        $employee['name'],
        $employee['department'],
        $employee['job_title'],
        $employee['email'],
        $employee['contact_number'],
        $employee['total_leaves'],
        $employee['approved_leaves'],
        $employee['pending_leaves'],
        $employee['rejected_leaves']
    ]);
}

fclose($output);
?> 