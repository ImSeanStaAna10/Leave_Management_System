<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'db.php';

// Check if user is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.html');
    exit();
}

$type = $_GET['type'] ?? '';
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

$filename = 'report_' . $type . '_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

switch ($type) {
    case 'trends':
        fputcsv($output, ['Month-Year', 'Unique Employees', 'Total Leave Days']);
        $sql = "SELECT 
                    DATE_FORMAT(start_date, '%Y-%m') as month_year,
                    COUNT(DISTINCT user_id) as unique_employees,
                    SUM(duration) as total_days
                FROM leaves 
                WHERE status = 'approved'
                AND start_date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(start_date, '%Y-%m')
                ORDER BY month_year";
        $result = $conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                date('M Y', strtotime($row['month_year'] . '-01')),
                $row['unique_employees'],
                $row['total_days']
            ]);
        }
        break;

    case 'types':
        fputcsv($output, ['Leave Type', 'Count', 'Total Days']);
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
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['leave_type'],
                $row['count'],
                $row['total_days']
            ]);
        }
        break;

    case 'monthly':
        fputcsv($output, ['Employee Name', 'Department', 'Leave Type', 'Start Date', 'End Date', 'Duration', 'Status']);
        $sql = "SELECT 
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
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['employee_name'],
                $row['department'],
                $row['leave_type'],
                date('d-m-Y', strtotime($row['start_date'])),
                date('d-m-Y', strtotime($row['end_date'])),
                $row['duration'],
                ucfirst($row['status'])
            ]);
        }
        break;

    default:
        fputcsv($output, ['Error', 'Invalid report type']);
        break;
}

fclose($output);
?> 