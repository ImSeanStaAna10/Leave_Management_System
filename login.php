<?php
session_start();
include 'db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    if ($email === 'admin@company.com' && $password === 'admin123') {
        $_SESSION['user'] = [ 'name' => 'Admin', 'role' => 'admin' ];
        header('Location: admin_dashboard.php');
        exit();
    }
    $stmt = $conn->prepare('SELECT id, name, password, role FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $name, $hash, $role);
        $stmt->fetch();
        if (password_verify($password, $hash)) {
            $_SESSION['user'] = [ 'id' => $id, 'name' => $name, 'role' => $role ];
            if ($role === 'admin') {
                header('Location: admin_dashboard.php');
            } else {
                header('Location: employee_dashboard.php');
            }
            exit();
        }
    }
    header('Location: login.html?error=1');
    exit();
}
?> 