<?php
$host = "localhost";
$username = "root";
$password = ""; // Default password is empty in XAMPP
$database = "leave_management";

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
