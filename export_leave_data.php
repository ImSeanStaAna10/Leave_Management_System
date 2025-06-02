<?php
session_start();
include 'db.php';

// Check if user is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.html');
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$employee_filter = isset($_GET['employee_filter']) ? $_GET['employee_filter'] : '';
$leave_type_filter = isset($_GET['leave_type_filter']) ? $_GET['leave_type_filter'] : '';

// Build query with filters
$sql = 'SELECT l.id, l.user_id, u.name as employee_name, lt.name as leave_type, 
        l.purpose, l.duration, l.status, l.start_date, l.end_date, l.applied_at 
        FROM leaves l 
        JOIN users u ON l.user_id = u.id 
        JOIN leave_types lt ON l.leave_type = lt.id 
        WHERE 1=1';

$params = [];
$types = '';

if ($status_filter) {
    $sql .= ' AND l.status = ?';
    $params[] = $status_filter;
    $types .= 's';
}

if ($date_from) {
    $sql .= ' AND l.start_date >= ?';
    $params[] = $date_from;
    $types .= 's';
}

if ($date_to) {
    $sql .= ' AND l.end_date <= ?';
    $params[] = $date_to;
    $types .= 's';
}

if ($employee_filter) {
    $sql .= ' AND l.user_id = ?';
    $params[] = $employee_filter;
    $types .= 'i';
}

if ($leave_type_filter) {
    $sql .= ' AND l.leave_type = ?';
    $params[] = $leave_type_filter;
    $types .= 'i';
}

$sql .= ' ORDER BY l.applied_at DESC';

// Prepare and execute query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get the export format
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';

if ($format === 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="leave_requests_' . date('Y-m-d') . '.csv"');
    
    // Create CSV file
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for proper Excel encoding
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add headers
    fputcsv($output, ['ID', 'Employee', 'Leave Type', 'Start Date', 'End Date', 'Duration', 'Status', 'Purpose', 'Applied At']);
    
    // Add data
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['employee_name'],
            $row['leave_type'],
            $row['start_date'],
            $row['end_date'],
            $row['duration'],
            $row['status'],
            $row['purpose'],
            $row['applied_at']
        ]);
    }
    
    fclose($output);
} elseif ($format === 'pdf') {
    // Include TCPDF library
    require_once('tcpdf/tcpdf.php');
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Leave Management System');
    $pdf->SetTitle('Leave Requests Report');
    
    // Set default header data
    $pdf->SetHeaderData('', 0, 'Leave Requests Report', date('Y-m-d'));
    
    // Set header and footer fonts
    $pdf->setHeaderFont(Array('helvetica', '', 12));
    $pdf->setFooterFont(Array('helvetica', '', 8));
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Create the table
    $html = '<table border="1" cellpadding="4">
        <thead>
            <tr style="background-color: #f0f0f0;">
                <th>ID</th>
                <th>Employee</th>
                <th>Leave Type</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Duration</th>
                <th>Status</th>
                <th>Purpose</th>
                <th>Applied At</th>
            </tr>
        </thead>
        <tbody>';
    
    while ($row = $result->fetch_assoc()) {
        $html .= '<tr>
            <td>' . $row['id'] . '</td>
            <td>' . htmlspecialchars($row['employee_name']) . '</td>
            <td>' . htmlspecialchars($row['leave_type']) . '</td>
            <td>' . $row['start_date'] . '</td>
            <td>' . $row['end_date'] . '</td>
            <td>' . $row['duration'] . '</td>
            <td>' . $row['status'] . '</td>
            <td>' . htmlspecialchars($row['purpose']) . '</td>
            <td>' . $row['applied_at'] . '</td>
        </tr>';
    }
    
    $html .= '</tbody></table>';
    
    // Output the HTML content
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Close and output PDF document
    $pdf->Output('leave_requests_' . date('Y-m-d') . '.pdf', 'D');
} else {
    // Invalid format
    header('Location: admin_dashboard.php#request-list');
    exit();
}
?> 