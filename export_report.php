<?php
session_start();
include 'db.php';

// Check if user is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.html');
    exit();
}

// Get parameters
$type = $_GET['type'] ?? '';
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$format = $_GET['format'] ?? 'csv';

// Base query for monthly report
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
$result = $stmt->get_result();

// Get trends data if needed
if ($type === 'trends') {
    $trends_sql = "SELECT 
                    DATE_FORMAT(start_date, '%Y-%m') as month_year,
                    COUNT(DISTINCT user_id) as unique_employees,
                    SUM(duration) as total_days
                FROM leaves 
                WHERE status = 'approved'
                AND start_date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(start_date, '%Y-%m')
                ORDER BY month_year";
    $trends_result = $conn->query($trends_sql);
}

// Set filename
$filename = $type === 'trends' ? 
    "leave_trends_report_{$year}" : 
    "monthly_leave_report_{$year}_{$month}";

if ($format === 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    // Create CSV file
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if ($type === 'trends') {
        // Write trends data
        fputcsv($output, ['Month', 'Unique Employees', 'Total Leave Days']);
        while ($row = $trends_result->fetch_assoc()) {
            fputcsv($output, [
                date('F Y', strtotime($row['month_year'] . '-01')),
                $row['unique_employees'],
                $row['total_days']
            ]);
        }
    } else {
        // Write monthly report data
        fputcsv($output, ['Employee', 'Total Leaves', 'Total Days', 'Leave Types']);
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['employee_name'],
                $row['total_leaves'],
                $row['total_days'],
                $row['leave_types']
            ]);
        }
    }
    
    fclose($output);
} else if ($format === 'pdf') {
    // Include TCPDF library
    require_once('tcpdf/tcpdf.php');
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Leave Management System');
    $pdf->SetTitle($filename);
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Add title
    $pdf->SetFont('helvetica', 'B', 16);
    $title = $type === 'trends' ? 
        "Leave Trends Report - {$year}" : 
        "Monthly Leave Report - " . date('F Y', mktime(0, 0, 0, $month, 1, $year));
    $pdf->Cell(0, 10, $title, 0, 1, 'C');
    $pdf->Ln(10);
    
    // Create table
    $pdf->SetFont('helvetica', 'B', 12);
    
    if ($type === 'trends') {
        // Add trends table
        $pdf->Cell(60, 7, 'Month', 1);
        $pdf->Cell(60, 7, 'Unique Employees', 1);
        $pdf->Cell(60, 7, 'Total Leave Days', 1);
        $pdf->Ln();
        
        $pdf->SetFont('helvetica', '', 10);
        while ($row = $trends_result->fetch_assoc()) {
            $pdf->Cell(60, 6, date('F Y', strtotime($row['month_year'] . '-01')), 1);
            $pdf->Cell(60, 6, $row['unique_employees'], 1);
            $pdf->Cell(60, 6, $row['total_days'], 1);
            $pdf->Ln();
        }
    } else {
        // Add monthly report table
        $pdf->Cell(50, 7, 'Employee', 1);
        $pdf->Cell(30, 7, 'Total Leaves', 1);
        $pdf->Cell(30, 7, 'Total Days', 1);
        $pdf->Cell(80, 7, 'Leave Types', 1);
        $pdf->Ln();
        
        $pdf->SetFont('helvetica', '', 10);
        while ($row = $result->fetch_assoc()) {
            $pdf->Cell(50, 6, $row['employee_name'], 1);
            $pdf->Cell(30, 6, $row['total_leaves'], 1);
            $pdf->Cell(30, 6, $row['total_days'], 1);
            $pdf->Cell(80, 6, $row['leave_types'], 1);
            $pdf->Ln();
        }
    }
    
    // Output PDF
    $pdf->Output($filename . '.pdf', 'D');
} else {
    // Invalid format
    header('Location: reports.php');
    exit();
} 