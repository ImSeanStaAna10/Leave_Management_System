<?php
session_start();
include 'db.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'employee') {
    header('Location: login.html');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user']['id'];
    $leave_type = $_POST['leave_type'];
    $purpose = $_POST['purpose'];
    $duration = $_POST['duration'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $stmt = $conn->prepare('INSERT INTO leaves (user_id, leave_type, purpose, duration, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('iissss', $user_id, $leave_type, $purpose, $duration, $start_date, $end_date);
    $stmt->execute();
    header('Location: employee_dashboard.php');
    exit();
}
?> 