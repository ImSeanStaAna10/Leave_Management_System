<?php
include 'db.php';

header('Content-Type: application/json');

if (isset($_GET['email'])) {
    $email = $_GET['email'];
    
    // Prepare statement to check if email exists
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    
    // Return JSON response
    echo json_encode([
        'exists' => $stmt->num_rows > 0
    ]);
} else {
    echo json_encode([
        'error' => 'Email parameter is required'
    ]);
}
?> 