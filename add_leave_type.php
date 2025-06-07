<?php
include 'db.php';

// Add description column if it doesn't exist
$conn->query("ALTER TABLE leave_types ADD COLUMN IF NOT EXISTS description TEXT");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $leave_type_name = filter_input(INPUT_POST, 'leave_type_name', FILTER_SANITIZE_STRING);
    $leave_type_days = filter_input(INPUT_POST, 'leave_type_days', FILTER_VALIDATE_INT);
    $leave_type_description = filter_input(INPUT_POST, 'leave_type_description', FILTER_SANITIZE_STRING);

    // Basic validation
    if ($leave_type_name && $leave_type_days !== false && $leave_type_days >= 0) {
        // Get the next available ID
        $result = $conn->query('SELECT MAX(id) as max_id FROM leave_types');
        $row = $result->fetch_assoc();
        $next_id = ($row['max_id'] ?? 0) + 1;

        // Prepare and execute INSERT query with ID and description
        $stmt = $conn->prepare('INSERT INTO leave_types (id, name, days, description) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('isis', $next_id, $leave_type_name, $leave_type_days, $leave_type_description);

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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Leave Type</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .form-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group textarea {
            height: 120px;
            resize: vertical;
        }
        .submit-btn {
            background: #2ecc71;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .submit-btn:hover {
            background: #27ae60;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #3498db;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Add New Leave Type</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="leave_type_name">Leave Type Name:</label>
                <input type="text" id="leave_type_name" name="leave_type_name" required>
            </div>
            
            <div class="form-group">
                <label for="leave_type_days">Number of Days:</label>
                <input type="number" id="leave_type_days" name="leave_type_days" min="0" required>
            </div>
            
            <div class="form-group">
                <label for="leave_type_description">Description & Policy:</label>
                <textarea id="leave_type_description" name="leave_type_description" required 
                    placeholder="Enter detailed description and policy for this leave type. Include eligibility criteria, documentation requirements, and any special conditions."></textarea>
            </div>
            
            <button type="submit" class="submit-btn">Add Leave Type</button>
        </form>
        <a href="admin_dashboard.php#leave-types" class="back-link">← Back to Leave Types</a>
    </div>
</body>
</html>
<?php $conn->close(); ?> 