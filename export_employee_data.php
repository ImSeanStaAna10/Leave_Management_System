<?php
session_start();
include 'db.php';

// Check if user is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.html');
    exit();
}

// Get parameters
$format = $_GET['format'] ?? 'csv';
$department_filter = $_GET['department_filter'] ?? '';

// Base query for employee data
$sql = 'SELECT employee_id, name, email, job_title, department, contact_number, date_of_joining FROM users WHERE role = "employee" ';

// Add department filter if applied
if (!empty($department_filter)) {
    $sql .= " AND department = ?";
}

$sql .= ' ORDER BY name';

$stmt = $conn->prepare($sql);

// Bind parameters if department filter is applied
if (!empty($department_filter)) {
    $stmt->bind_param('s', $department_filter);
}

$stmt->execute();
$result = $stmt->get_result();

// Set filename
$filename = 'employee_list_report';
if (!empty($department_filter)) {
    $filename .= '_' . str_replace(' ', '_', $department_filter);
}

if ($format === 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    // Create CSV file
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write CSV header
    fputcsv($output, ['Employee ID', 'Name', 'Email', 'Job Title', 'Department', 'Contact Number', 'Date of Joining']);
    
    // Write employee data
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['employee_id'],
            $row['name'],
            $row['email'],
            $row['job_title'],
            $row['department'],
            $row['contact_number'],
            $row['date_of_joining']
        ]);
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
    $title = 'Employee List Report';
    if (!empty($department_filter)) {
        $title .= ' for ' . htmlspecialchars($department_filter);
    }
    $pdf->Cell(0, 10, $title, 0, 1, 'C');
    $pdf->Ln(10);
    
    // Create table header
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(30, 7, 'Employee ID', 1);
    $pdf->Cell(40, 7, 'Name', 1);
    $pdf->Cell(50, 7, 'Email', 1);
    $pdf->Cell(30, 7, 'Department', 1);
    $pdf->Cell(30, 7, 'Job Title', 1);
    $pdf->Cell(30, 7, 'Contact', 1);
    $pdf->Cell(30, 7, 'Joining Date', 1);

    $pdf->Ln();
    
    // Add table data
    $pdf->SetFont('helvetica', '', 8);
    while ($row = $result->fetch_assoc()) {
        $pdf->Cell(30, 6, htmlspecialchars($row['employee_id']), 1);
        $pdf->Cell(40, 6, htmlspecialchars($row['name']), 1);
        $pdf->Cell(50, 6, htmlspecialchars($row['email']), 1);
        $pdf->Cell(30, 6, htmlspecialchars($row['department']), 1);
        $pdf->Cell(30, 6, htmlspecialchars($row['job_title']), 1);
        $pdf->Cell(30, 6, htmlspecialchars($row['contact_number']), 1);
        $pdf->Cell(30, 6, htmlspecialchars($row['date_of_joining']), 1);
        $pdf->Ln();
    }
    
    // Output PDF
    $pdf->Output($filename . '.pdf', 'D');
} else {
    // Invalid format
    header('Location: admin_dashboard.php#employee-list');
    exit();
}

$conn->close();
?> 