<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $leave_type_name = filter_input(INPUT_POST, 'leave_type_name', FILTER_SANITIZE_STRING);
    $leave_type_days = filter_input(INPUT_POST, 'leave_type_days', FILTER_VALIDATE_INT);

    // Basic validation
    if ($leave_type_name && $leave_type_days !== false && $leave_type_days >= 0) {
        // Get the next available ID
        $result = $conn->query('SELECT MAX(id) as max_id FROM leave_types');
        $row = $result->fetch_assoc();
        $next_id = ($row['max_id'] ?? 0) + 1;

        // Prepare and execute INSERT query with ID
        $stmt = $conn->prepare('INSERT INTO leave_types (id, name, days) VALUES (?, ?, ?)');
        $stmt->bind_param('isi', $next_id, $leave_type_name, $leave_type_days);

        if ($stmt->execute()) {
            // Redirect back to admin dashboard leave types section
            header('Location: admin_dashboard.php#leave-types');
            exit();
        } else {
            // Handle database error
            echo 'Error adding leave type: ' . $stmt->error;
        }

        $stmt->close();
    } else {
        // Handle invalid input
        echo 'Invalid input data.';
    }
}

$conn->close();
?> 