<?php
session_start();
include 'db.php';

// Generate and validate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function validate_csrf_token($token) {
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.html');
    exit();
}

// Handle leave request actions (Approve/Reject)
if (isset($_POST['action']) && isset($_POST['leave_id']) && ($_POST['action'] === 'approve' || $_POST['action'] === 'reject')) {
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        die('CSRF token validation failed.'); // Or handle the error more gracefully
    }

    $action = $_POST['action'];
    $leave_id = $_POST['leave_id'];

        $status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = $conn->prepare('UPDATE leaves SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $status, $leave_id);
        $stmt->execute();
    
    // Redirect back to the requests list after action
    header('Location: admin_dashboard.php#request-list');
    exit();
}

// Handle delete leave type action
if (isset($_POST['action']) && $_POST['action'] === 'delete_leave_type' && isset($_POST['leave_type_id'])) {

    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        die('CSRF token validation failed.'); // Or handle the error more gracefully
    }

    $leave_type_id = $_POST['leave_type_id'];

    // Add a check to ensure no leaves are associated with this type before deleting
    $check_leaves_stmt = $conn->prepare('SELECT COUNT(*) FROM leaves WHERE leave_type = ?');
    $check_leaves_stmt->bind_param('i', $leave_type_id);
    $check_leaves_stmt->execute();
    $check_leaves_stmt->bind_result($leaves_count);
    $check_leaves_stmt->fetch();
    $check_leaves_stmt->close();

    if ($leaves_count == 0) {
        // Prepare and execute DELETE query
        $stmt = $conn->prepare('DELETE FROM leave_types WHERE id = ?');
        $stmt->bind_param('i', $leave_type_id);
        if ($stmt->execute()) {
            // Redirect back to admin dashboard leave types section with success message
            header('Location: admin_dashboard.php#leave-types');
            exit();
        } else {
            // Handle database error
            echo 'Error deleting leave type: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        // Handle case where leaves are associated with the type
        echo 'Cannot delete leave type as there are leaves associated with it.';
    }
    
    // Redirect back to admin dashboard leave types section
    header('Location: admin_dashboard.php#leave-types');
    exit();
}

// Handle employee management actions
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'add_employee') {
        // Add new employee logic here
        $employee_id = $_POST['employee_id'];
        $name = $_POST['name'];
        $email = $_POST['email'];
        
        // Check if email already exists
        $check_email = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $check_email->bind_param('s', $email);
        $check_email->execute();
        $check_email->store_result();
        
        if ($check_email->num_rows > 0) {
            // Redirect back to form without error message
            header('Location: admin_dashboard.php#employee-list');
            exit();
        } else {
            // Add password validation/confirmation check here if needed
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $job_title = $_POST['job_title'];
            $contact_number = $_POST['contact_number'];
            $birthday = $_POST['birthday'];
            $department = $_POST['department'];
            $gender = $_POST['gender'];
            $date_of_joining = $_POST['date_of_joining'];
            $address = $_POST['address'];
            $role = $_POST['role'];

            // Handle profile picture upload
            $profile_picture = 'default-avatar.png'; // Default profile picture
            $upload_error = NULL; // Initialize error message

            // Log $_FILES array to see if file is received
            error_log('$_FILES array: ' . print_r($_FILES, true));

            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['profile_picture']['tmp_name'];
                $fileName = $_FILES['profile_picture']['name'];
                $fileSize = $_FILES['profile_picture']['size'];
                $fileType = $_FILES['profile_picture']['type'];
                $fileError = $_FILES['profile_picture']['error'];

                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));
                
                // Validate file type
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($fileExtension, $allowedExtensions)) {
                    $upload_error = "Invalid file type. Please upload JPG, JPEG, PNG or GIF.";
                    error_log('Invalid file type uploaded: ' . $fileExtension);
                } else {
                    $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                    $uploadFileDir = 'uploads/'; // Corrected directory path
                    $dest_path = $uploadFileDir . $newFileName;

                    // Check if directory exists and is writable, or attempt to create it
                    if (!is_dir($uploadFileDir)) {
                        if (!mkdir($uploadFileDir, 0777, true)) {
                            $upload_error = "Failed to create upload directory.";
                            error_log('Failed to create upload directory: ' . $uploadFileDir);
                        }
                    }

                    // Check if directory is writable after attempting to create it
                    if (is_writable($uploadFileDir)) {
                        if(move_uploaded_file($fileTmpPath, $dest_path)) {
                            // File moved successfully, set the profile picture path
                            $profile_picture = $dest_path;
                        } else {
                            $upload_error = "Failed to upload image. Please try again.";
                            error_log('Failed to move uploaded file.');
                            error_log('Source: ' . $fileTmpPath);
                            error_log('Destination: ' . $dest_path);
                            error_log('File Error Code: ' . $fileError);
                        }
                    } else {
                        $upload_error = "Upload directory is not writable. Please contact administrator.";
                        error_log('Upload directory is not writable: ' . $uploadFileDir);
                    }
                }
            } else if (isset($_FILES['profile_picture'])) {
                $upload_error = "File upload error. Please try again.";
                error_log('File upload encountered an error. Error Code: ' . $_FILES['profile_picture']['error']);
            }

            // Only proceed with database insertion if there was no upload error
            if (!$upload_error) {
                try {
                    $stmt = $conn->prepare('INSERT INTO users (employee_id, name, email, password, job_title, contact_number, birthday, department, gender, date_of_joining, address, role, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->bind_param('sssssssssssss', $employee_id, $name, $email, $password, $job_title, $contact_number, $birthday, $department, $gender, $date_of_joining, $address, $role, $profile_picture);
                    
                    if ($stmt->execute()) {
                        header('Location: admin_dashboard.php#employee-list');
                        exit();
                    } else {
                        $upload_error = "Failed to save employee data. Please try again.";
                        error_log('Database insertion error: ' . $stmt->error);
                    }
                } catch (mysqli_sql_exception $e) {
                    if ($e->getCode() == 1062) { // Duplicate entry error code
                        header('Location: admin_dashboard.php#employee-list');
                        exit();
                    } else {
                        $upload_error = "An error occurred while saving the data. Please try again.";
                        error_log('Database error: ' . $e->getMessage());
                    }
                }
            }
        }
    } elseif ($_POST['action'] === 'edit_employee') {
        // Edit employee logic here
        error_log('*** Starting edit_employee action ***'); // Log start of edit action
        error_log('POST data received for edit: ' . print_r($_POST, true)); // Debug log for POST data
        $id = $_POST['id'];
        $employee_id = $_POST['employee_id'];
        $name = $_POST['name'];
        $email = $_POST['email'];
        $job_title = $_POST['job_title'];
        $contact_number = $_POST['contact_number'];
        $birthday = $_POST['birthday'];
        $department = $_POST['department'];
        $gender = $_POST['gender'];
        $date_of_joining = $_POST['date_of_joining'];
        $address = $_POST['address'];
        $role = $_POST['role'];

        // Fetch current employee data
        $stmt_fetch = $conn->prepare('SELECT * FROM users WHERE id = ?');
        $stmt_fetch->bind_param('i', $id);
        $stmt_fetch->execute();
        $result = $stmt_fetch->get_result();
        $current_data = $result->fetch_assoc();
        $stmt_fetch->close();

        // Prepare update query based on changed fields
        $update_fields = [];
        $types = '';
        $params = [];

        if ($current_data['employee_id'] !== $employee_id) {
            $update_fields[] = 'employee_id = ?';
            $types .= 's';
            $params[] = $employee_id;
        }
        if ($current_data['name'] !== $name) {
            $update_fields[] = 'name = ?';
            $types .= 's';
            $params[] = $name;
        }
        if ($current_data['email'] !== $email) {
            $update_fields[] = 'email = ?';
            $types .= 's';
            $params[] = $email;
        }
        if ($current_data['job_title'] !== $job_title) {
            $update_fields[] = 'job_title = ?';
            $types .= 's';
            $params[] = $job_title;
        }
        if ($current_data['contact_number'] !== $contact_number) {
            $update_fields[] = 'contact_number = ?';
            $types .= 's';
            $params[] = $contact_number;
        }
        if ($current_data['birthday'] !== $birthday) {
            $update_fields[] = 'birthday = ?';
            $types .= 's';
            $params[] = $birthday;
        }
        if ($current_data['department'] !== $department) {
            $update_fields[] = 'department = ?';
            $types .= 's';
            $params[] = $department;
        }
        if ($current_data['gender'] !== $gender) {
            $update_fields[] = 'gender = ?';
            $types .= 's';
            $params[] = $gender;
        }
        if ($current_data['date_of_joining'] !== $date_of_joining) {
            $update_fields[] = 'date_of_joining = ?';
            $types .= 's';
            $params[] = $date_of_joining;
        }
        if ($current_data['address'] !== $address) {
            $update_fields[] = 'address = ?';
            $types .= 's';
            $params[] = $address;
        }
        if ($current_data['role'] !== $role) {
            $update_fields[] = 'role = ?';
            $types .= 's';
            $params[] = $role;
        }

        // Handle profile picture upload for editing
        $profile_picture = NULL; // Initialize to NULL
        $upload_error = NULL; // Initialize error message

        // Log $_FILES array to see if file is received
        error_log('$_FILES array for edit: ' . print_r($_FILES, true));
        error_log('POST data for edit: ' . print_r($_POST, true)); // Log POST data
        error_log('Received employee ID for edit: ' . $id); // Log received ID

        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['profile_picture']['tmp_name'];
            $fileName = $_FILES['profile_picture']['name'];
            $fileSize = $_FILES['profile_picture']['size'];
            $fileType = $_FILES['profile_picture']['type'];
            $fileError = $_FILES['profile_picture']['error'];

            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            
            // Validate file type
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($fileExtension, $allowedExtensions)) {
                $upload_error = "Invalid file type. Please upload JPG, JPEG, PNG or GIF.";
                error_log('Invalid file type uploaded for edit: ' . $fileExtension);
            } else {
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $uploadFileDir = 'uploads/'; // Corrected directory path
                $dest_path = $uploadFileDir . $newFileName;

                // Check if directory exists and is writable, or attempt to create it
                if (!is_dir($uploadFileDir)) {
                    if (!mkdir($uploadFileDir, 0777, true)) {
                        $upload_error = "Failed to create upload directory for edit.";
                        error_log('Failed to create upload directory for edit: ' . $uploadFileDir);
                    }
                }

                // Check if directory is writable after attempting to create it
                if (is_writable($uploadFileDir)) {
                    if(move_uploaded_file($fileTmpPath, $dest_path)) {
                        // File moved successfully, set the profile picture path
                        $profile_picture = $dest_path;
                        error_log('Profile picture uploaded and path set to: ' . $profile_picture); // Log uploaded picture path
                    } else {
                        $upload_error = "Failed to upload image for edit. Please try again.";
                        error_log('Failed to move uploaded file for edit.');
                        error_log('Source: ' . $fileTmpPath);
                        error_log('Destination: ' . $dest_path);
                        error_log('File Error Code: ' . $fileError);
                    }
                } else {
                    $upload_error = "Upload directory is not writable for edit. Please contact administrator.";
                    error_log('Upload directory is not writable for edit: ' . $uploadFileDir);
                }
            }
        } else if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_NO_FILE) {
             // No new file was uploaded, keep existing if it exists
             // Fetch existing profile picture path
             $stmt_fetch_pic = $conn->prepare('SELECT profile_picture FROM users WHERE id = ?');
             $stmt_fetch_pic->bind_param('i', $id);
             $stmt_fetch_pic->execute();
             $stmt_fetch_pic->bind_result($existing_profile_picture);
             $stmt_fetch_pic->fetch();
             $stmt_fetch_pic->close();
             $profile_picture = $existing_profile_picture;
        } else if (isset($_FILES['profile_picture'])) {
             $upload_error = "File upload error. Please try again.";
             error_log('File upload encountered an error during edit. Error Code: ' . $_FILES['profile_picture']['error']);
        }

        // Only proceed with database update if there was no upload error
        if (!$upload_error) {
            if ($profile_picture !== NULL) {
                $update_fields[] = 'profile_picture = ?';
                $types .= 's';
                $params[] = $profile_picture;
            }

            if (!empty($update_fields)) {
                $types .= 'i'; // Add type for id
                $params[] = $id; // Add id to params

                $sql = 'UPDATE users SET ' . implode(', ', $update_fields) . ' WHERE id = ?';
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$params);
                
                if ($stmt->execute()) {
                    error_log('Database update successful for employee ID: ' . $id); // Log successful update
                    header('Location: admin_dashboard.php#employee-list');
                    exit();
                } else {
                    $upload_error = "Failed to update employee data. Please try again.";
                    error_log('Database update error: ' . $stmt->error);
                }
            } else {
                // No fields to update
                header('Location: admin_dashboard.php#employee-list');
                exit();
            }
        }
    }
}

// Get all employees
$employees_sql = 'SELECT * FROM users WHERE role = "employee" ORDER BY name';
$employees_result = $conn->query($employees_sql);

// Get leave requests
$sql = 'SELECT l.id, l.user_id, u.name, lt.name as leave_type, l.purpose, l.duration, l.status, l.start_date, l.end_date, l.applied_at FROM leaves l JOIN users u ON l.user_id = u.id JOIN leave_types lt ON l.leave_type = lt.id ORDER BY l.applied_at DESC';
$result = $conn->query($sql);

// Dashboard counts and recent requests
$leave_types_count = $conn->query('SELECT COUNT(*) as count FROM leave_types')->fetch_assoc()['count'];
$employees_count = $conn->query('SELECT COUNT(*) as count FROM users WHERE role = "employee"')->fetch_assoc()['count'];
$approved_count = $conn->query('SELECT COUNT(*) as count FROM leaves WHERE status = "approved"')->fetch_assoc()['count'];
$rejected_count = $conn->query('SELECT COUNT(*) as count FROM leaves WHERE status = "rejected"')->fetch_assoc()['count'];
$pending_count = $conn->query('SELECT COUNT(*) as count FROM leaves WHERE status = "pending"')->fetch_assoc()['count'];
$recent_requests = $conn->query('
    SELECT l.applied_at, u.name as employee, lt.name as leave_type, l.start_date, l.end_date, l.purpose, l.status
    FROM leaves l
    JOIN users u ON l.user_id = u.id
    JOIN leave_types lt ON l.leave_type = lt.id
    ORDER BY l.applied_at DESC
    LIMIT 10
');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --dark: #212529;
            --light: #f8f9fa;
            --sidebar-width: 250px;
            --header-height: 60px;

            /* Light theme variables */
            --body-bg-light: #f5f7fb;
            --main-content-bg-light: #f5f7fb;
            --card-bg-light: #fff;
            --card-header-bg-light: #fff;
            --text-color-light: #212529;
            --secondary-text-color-light: #6c757d;
            --border-color-light: #e9ecef;
            --table-border-color-light: #edf2f9;
            --table-header-bg-light: var(--primary);
            --table-header-color-light: white;
            --table-row-hover-bg-light: rgba(67, 97, 238, 0.05);
            --modal-content-bg-light: #fff;
            --modal-header-border-light: #f0f0f0;
            --modal-title-color-light: var(--dark);
            --names-list-bg-light: #f8f9fa;
            --names-item-hover-bg-light: #e3f2fd;
            --leave-box-bg-light: white;
            --leave-box-border-left-light: var(--primary);
            --button-primary-bg-light: var(--primary);
            --button-primary-color-light: white;
            --button-outline-secondary-color-light: #6c757d;
            --button-success-bg-light: #28a745;
            --button-success-color-light: white;
            --button-danger-bg-light: #dc3545;
            --button-danger-color-light: white;

            /* Dark theme variables */
            --body-bg-dark: #1a1a1a;
            --main-content-bg-dark: #1a1a1a;
            --card-bg-dark: #2d2d2d;
            --card-header-bg-dark: #3c3c3c;
            --text-color-dark: #e0e0e0;
            --secondary-text-color-dark: #b0b0b0;
            --border-color-dark: #3a3a3a;
            --table-border-color-dark: #444;
            --table-header-bg-dark: #555;
            --table-header-color-dark: #eee;
            --table-row-hover-bg-dark: rgba(100, 100, 100, 0.1);
            --modal-content-bg-dark: #2d2d2d;
            --modal-header-border-dark: #444;
            --modal-title-color-dark: #eee;
            --names-list-bg-dark: #3a3a3a;
            --names-item-hover-bg-dark: #555;
            --leave-box-bg-dark: #2d2d2d;
            --leave-box-border-left-dark: var(--primary);
            --button-primary-bg-dark: #5a7aff;
            --button-primary-color-dark: white;
            --button-outline-secondary-color-dark: #b0b0b0;
            --button-success-bg-dark: #3cb371;
            --button-success-color-dark: white;
            --button-danger-bg-dark: #ff6347;
            --button-danger-color-dark: white;

            /* Default theme (Light) */
            --body-bg: var(--body-bg-light);
            --main-content-bg: var(--main-content-bg-light);
            --card-bg: var(--card-bg-light);
            --card-header-bg: var(--card-header-bg-light);
            --text-color: var(--text-color-light);
            --secondary-text-color: var(--secondary-text-color-light);
            --border-color: var(--border-color-light);
            --table-border-color: var(--table-border-color-light);
            --table-header-bg: var(--table-header-bg-light);
            --table-header-color: var(--table-header-color-light);
            --table-row-hover-bg: var(--table-row-hover-bg-light);
            --modal-content-bg: var(--modal-content-bg-light);
            --modal-header-border: var(--modal-header-border-light);
            --modal-title-color: var(--modal-title-color-light);
            --names-list-bg: var(--names-list-bg-light);
            --names-item-hover-bg: var(--names-item-hover-bg-light);
            --leave-box-bg: var(--leave-box-bg-light);
            --leave-box-border-left: var(--leave-box-border-left-light);
            --button-primary-bg: var(--button-primary-bg-light);
            --button-primary-color: var(--button-primary-color-light);
            --button-outline-secondary-color: var(--button-outline-secondary-color-light);
            --button-success-bg: var(--button-success-bg-light);
            --button-success-color: var(--button-success-color-light);
            --button-danger-bg: var(--button-danger-bg-light);
            --button-danger-color: var(--button-danger-color-light);
        }

        /* Dark mode styles */
        body.dark-mode {
            --body-bg: var(--body-bg-dark);
            --main-content-bg: var(--main-content-bg-dark);
            --card-bg: var(--card-bg-dark);
            --card-header-bg: var(--card-header-bg-dark);
            --text-color: var(--text-color-dark);
            --secondary-text-color: var(--secondary-text-color-dark);
            --border-color: var(--border-color-dark);
            --table-border-color: var(--table-border-color-dark);
            --table-header-bg: var(--table-header-bg-dark);
            --table-header-color: var(--table-header-color-dark);
            --table-row-hover-bg: var(--table-row-hover-bg-dark);
            --modal-content-bg: var(--modal-content-bg-dark);
            --modal-header-border: var(--modal-header-border-dark);
            --modal-title-color: var(--modal-title-color-dark);
            --names-list-bg: var(--names-list-bg-dark);
            --names-item-hover-bg: var(--names-item-hover-bg-dark);
            --leave-box-bg: var(--leave-box-bg-dark);
            --leave-box-border-left: var(--leave-box-border-left-dark);
            --button-primary-bg: var(--button-primary-bg-dark);
            --button-primary-color: var(--button-primary-color-dark);
            --button-outline-secondary-color: var(--button-outline-secondary-color-dark);
            --button-success-bg: var(--button-success-bg-dark);
            --button-success-color: var(--button-success-color-dark);
            --button-danger-bg: var(--button-danger-bg-dark);
            --button-danger-color: var(--button-danger-color-dark);
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--body-bg);
            overflow-x: hidden;
            color: var(--text-color);
        }
        
        /* Sidebar styles */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: linear-gradient(180deg, var(--primary), var(--secondary));
            color: white;
            padding-top: var(--header-height);
            z-index: 100;
            box-shadow: 3px 0 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            display: flex; /* Added to push theme switcher to bottom */
            flex-direction: column; /* Added to push theme switcher to bottom */
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 15px;
            border-radius: 8px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
        }
        
        .sidebar .nav-link:hover, 
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.15);
            text-decoration: none;
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        .sidebar .logo {
            position: absolute;
            top: 15px;
            left: 20px;
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: all 0.3s ease;
            background-color: var(--main-content-bg);
            color: var(--text-color);
        }
        
        /* Header */
        .header {
            background: var(--card-bg);
            height: var(--header-height);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            position: fixed;
            top: 0;
            right: 0;
            left: var(--sidebar-width);
            z-index: 99;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            transition: all 0.3s ease;
            color: var(--text-color);
        }
        
        /* Dashboard cards */
        .dashboard-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
            margin-bottom: 20px;
            height: 100%;
            color: white; /* Text color for dashboard cards remains white */
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        
        .dashboard-card .card-body {
            padding: 20px;
        }
        
        .dashboard-card .card-title {
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        .dashboard-card .card-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .dashboard-card .card-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        
        /* Custom table styling */
        .custom-table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            background: var(--card-bg);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            color: var(--text-color);
        }
        
        .custom-table thead th {
            background: var(--table-header-bg);
            color: var(--table-header-color);
            padding: 15px 20px;
            font-weight: 600;
        }
        
        .custom-table tbody tr {
            transition: background 0.2s;
        }
        
        .custom-table tbody tr:hover {
            background-color: var(--table-row-hover-bg);
        }
        
        .custom-table tbody td {
            padding: 15px 20px;
            border-top: 1px solid var(--table-border-color);
        }
        
        /* Employee cards */
        .employee-card {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 20px;
            background: var(--card-bg);
            color: var(--text-color);
        }
        
        .employee-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }
        
        .employee-card .card-header {
            height: 100px;
            background: linear-gradient(120deg, var(--primary), var(--secondary));
            position: relative;
            margin-bottom: 60px;
            border: none;
        }
        
        .employee-card .profile-img {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            position: absolute;
            bottom: -45px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--card-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 4px solid var(--card-bg);
            box-shadow: 0 2px 15px rgba(0,0,0,0.2);
        }
        
        .employee-card .profile-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .employee-card .card-body {
            padding: 25px 15px 15px;
            text-align: center;
        }
        
        .employee-card .employee-name {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 5px;
            color: var(--text-color);
        }
        
        .employee-card .employee-title {
            color: var(--secondary-text-color);
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .employee-card .employee-contact {
            font-size: 0.9rem;
            color: var(--text-color);
        }
        
        /* Status badges */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Leave boxes */
        .leave-box {
            background: var(--leave-box-bg);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            position: relative;
            border-left: 4px solid var(--leave-box-border-left);
            color: var(--text-color);
        }
        
        .leave-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }
        
        .leave-box .employee-name {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 5px;
            color: var(--text-color);
        }
        
        .leave-box .leave-type {
            color: var(--secondary-text-color);
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .leave-box .leave-dates {
            font-size: 0.95rem;
            margin-bottom: 15px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .header {
                left: 0;
            }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1; /* Consider adding dark mode scrollbar styles */
        }
        
        ::-webkit-scrollbar-thumb {
            background: #c1c1c1; /* Consider adding dark mode scrollbar styles */
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8; /* Consider adding dark mode scrollbar styles */
        }
        
        /* Action buttons */
        .btn-action {
            padding: 5px 12px;
            font-size: 0.85rem;
            border-radius: 4px;
            margin-right: 5px;
        }
        
        .btn-approve {
            background: var(--button-success-bg);
            color: var(--button-success-color);
            border: none;
        }
        
        .btn-reject {
            background: var(--button-danger-bg);
            color: var(--button-danger-color);
            border: none;
        }
        
        /* Section titles */
        .section-title {
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
            color: var(--text-color);
        }
        
        .section-title i {
            margin-right: 10px;
            color: var(--primary);
        }
        
        /* Tab buttons */
        .tab-btn {
            background: var(--border-color);
            border: none;
            color: var(--secondary-text-color);
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            background: var(--primary);
            color: white;
        }
        
        /* Profile picture upload */
        .profile-picture-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            cursor: pointer;
            overflow: hidden;
        }
        
        .profile-picture-placeholder i {
            font-size: 3rem;
            color: #adb5bd;
        }
        
        .profile-picture-placeholder img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">LeaveManager</div>
        <div class="search-container mb-3 px-3">
            <div class="input-group">
                <input type="text" id="globalSearch" class="form-control" placeholder="Search employee...">
                <button class="btn btn-light" type="button" onclick="performSearch()">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="#dashboard">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#employee-list">
                    <i class="bi bi-people"></i> Employees
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#request-list">
                    <i class="bi bi-list-check"></i> Leave Requests
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#leave-types">
                    <i class="bi bi-card-list"></i> Leave Types
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="calendar_view.php">
                    <i class="bi bi-calendar"></i> Calendar View
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reports.php">
                    <i class="bi bi-bar-chart"></i> Reports
                </a>
            </li>
            <li class="nav-item mt-4">
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-left"></i> Logout
                </a>
            </li>
        </ul>

       
    </div>

    <!-- Header -->
    <div class="header">
        <button class="btn d-lg-none" type="button" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
        <div class="d-flex align-items-center">
            <div class="me-3">
                <i class="bi bi-person-circle fs-4"></i>
            </div>
            <div>
                <div class="fw-bold">Admin</div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Dashboard Section -->
        <div class="section" id="dashboard">
            <h3 class="section-title"><i class="bi bi-speedometer2"></i> Dashboard Overview</h3>
            
            <div class="row mb-4">
                <div class="col-md-6 col-lg-3">
                    <div class="dashboard-card bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="card-title">Leave Types</div>
                                    <div class="card-value"><?= $leave_types_count ?></div>
                                </div>
                                <div class="card-icon">
                                    <i class="bi bi-card-list"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="dashboard-card bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="card-title">Employees</div>
                                    <div class="card-value"><?= $employees_count ?></div>
                                </div>
                                <div class="card-icon">
                                    <i class="bi bi-people"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="dashboard-card bg-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="card-title">Approved</div>
                                    <div class="card-value"><?= $approved_count ?></div>
                                </div>
                                <div class="card-icon">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="dashboard-card bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="card-title">Pending</div>
                                    <div class="card-value"><?= $pending_count ?></div>
                                </div>
                                <div class="card-icon">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Leave Requests</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Request Date</th>
                                    <th>Employee</th>
                                    <th>Leave Type</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $recent_requests->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars(date('d-m-Y', strtotime($row['applied_at']))) ?></td>
                                    <td><?= htmlspecialchars($row['employee']) ?></td>
                                    <td><?= htmlspecialchars($row['leave_type']) ?></td>
                                    <td><?= htmlspecialchars(date('d-m-Y', strtotime($row['start_date']))) ?></td>
                                    <td><?= htmlspecialchars(date('d-m-Y', strtotime($row['end_date']))) ?></td>
                                    <td>
                                        <span class="d-inline-block text-truncate" style="max-width: 200px;" 
                                              data-bs-toggle="tooltip" data-bs-title="<?= htmlspecialchars($row['purpose']) ?>">
                                            <?= htmlspecialchars($row['purpose']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                            $status = strtolower($row['status']);
                                            $badge_class = $status === 'pending' ? 'badge-pending' : 
                                                          ($status === 'approved' ? 'badge-approved' : 'badge-rejected');
                                        ?>
                                        <span class="status-badge <?= $badge_class ?>">
                                            <?= ucfirst($status) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Employee List Section -->
        <div class="section" id="employee-list" style="display: none;">
            <h3 class="section-title"><i class="bi bi-people"></i> Employee Management</h3>
            
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Employee Directory</h5>
                    <button class="btn btn-primary" onclick="showAddEmployeeModal()">
                        <i class="bi bi-plus-lg me-1"></i> Add Employee
                    </button>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <form id="employeeFilterForm" method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Department</label>
                                <select name="department_filter" id="department_filter" class="form-select">
                                    <option value="">All Departments</option>
                                    <?php
                                    $departments_query = $conn->query('SELECT DISTINCT department FROM users WHERE role = "employee" ORDER BY department');
                                    while($dept = $departments_query->fetch_assoc()):
                                    ?>
                                    <option value="<?= htmlspecialchars($dept['department']) ?>" 
                                        <?= isset($_GET['department_filter']) && $_GET['department_filter'] === $dept['department'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['department']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">Apply</button>
                                <button type="button" onclick="resetEmployeeFilters()" class="btn btn-outline-secondary me-2">Reset</button>
                                <button type="button" class="btn btn-success" onclick="exportEmployeeData()">
                                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="row">
                        <?php
                        $employees_sql = 'SELECT * FROM users WHERE role = "employee"';
                        if (isset($_GET['department_filter']) && !empty($_GET['department_filter'])) {
                            $department_filter = $_GET['department_filter'];
                            $employees_sql .= " AND department = '$department_filter'";
                        }
                        $employees_sql .= ' ORDER BY name';
                        $employees_result = $conn->query($employees_sql);
                        
                        while($employee = $employees_result->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4 col-xl-3">
                            <div class="employee-card"
                                data-id="<?= htmlspecialchars($employee['id']) ?>"
                                data-employee_id="<?= htmlspecialchars($employee['employee_id']) ?>"
                                data-name="<?= htmlspecialchars($employee['name']) ?>"
                                data-email="<?= htmlspecialchars($employee['email']) ?>"
                                data-job_title="<?= htmlspecialchars($employee['job_title']) ?>"
                                data-contact_number="<?= htmlspecialchars($employee['contact_number']) ?>"
                                data-birthday="<?= htmlspecialchars($employee['birthday']) ?>"
                                data-department="<?= htmlspecialchars($employee['department']) ?>"
                                data-gender="<?= htmlspecialchars($employee['gender']) ?>"
                                data-date_of_joining="<?= htmlspecialchars($employee['date_of_joining']) ?>"
                                data-address="<?= htmlspecialchars($employee['address']) ?>"
                                data-role="<?= htmlspecialchars($employee['role']) ?>"
                                data-profile_picture="<?= htmlspecialchars($employee['profile_picture'] ?? '') ?>">
                                <div class="card-header">
                                    <div class="profile-img">
                                        <?php if (!empty($employee['profile_picture']) && file_exists($employee['profile_picture'])): ?>
                                            <img src="<?= htmlspecialchars($employee['profile_picture']) ?>" alt="Profile Picture">
                                        <?php else: ?>
                                            <i class="bi bi-person-circle" style="font-size: 3rem; color: #6c757d;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <button class="btn btn-sm btn-light position-absolute top-0 end-0 m-2" 
                                            onclick="openEditEmployeeModal(this)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="employee-name"><?= htmlspecialchars($employee['name']) ?></div>
                                    <div class="employee-title"><?= htmlspecialchars($employee['job_title']) ?></div>
                                    <div class="employee-contact">
                                        <div><i class="bi bi-envelope me-2"></i><?= htmlspecialchars($employee['email']) ?></div>
                                        <div class="mt-2"><i class="bi bi-telephone me-2"></i><?= htmlspecialchars($employee['contact_number']) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Request List Section -->
        <div class="section" id="request-list" style="display: none;">
            <h3 class="section-title"><i class="bi bi-list-check"></i> Leave Requests</h3>
            
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <button class="nav-link active" id="pendingTab" onclick="showTab('pending')">Pending Requests</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="historyTab" onclick="showTab('history')">Request History</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <button type="button" class="btn btn-success" onclick="exportToCSV()">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export
                        </button>
                    </div>
                    
                    <div id="pending-leaves">
                        <div class="row">
                            <?php 
                            $pendingBoxes = '';
                            $historyBoxes = '';
                            while($row = $result->fetch_assoc()):
                                ob_start(); ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="leave-box" data-userid="<?= $row['user_id'] ?>" data-leaveid="<?= $row['id'] ?>" data-status="<?= $row['status'] ?>">
                                        <div class="employee-name"><?= htmlspecialchars($row['name']) ?></div>
                                        <div class="leave-type"><?= htmlspecialchars($row['leave_type']) ?></div>
                                        <div class="leave-dates">
                                            <i class="bi bi-calendar me-1"></i> 
                                            <?= htmlspecialchars($row['start_date']) ?> to <?= htmlspecialchars($row['end_date']) ?>
                                            <span class="badge bg-light text-dark ms-2"><?= htmlspecialchars($row['duration']) ?> days</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <?php
                                                $status = strtolower($row['status']);
                                                $badge_class = $status === 'pending' ? 'badge-pending' : 
                                                              ($status === 'approved' ? 'badge-approved' : 'badge-rejected');
                                            ?>
                                            <span class="status-badge <?= $badge_class ?>">
                                                <?= ucfirst($status) ?>
                                            </span>
                                            <?php if($row['status'] === 'pending'): ?>
                                                <div>
                                                    <!-- Approve form -->
                                                    <form method="POST" action="admin_dashboard.php" style="display: inline;">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="leave_id" value="<?= $row['id'] ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                        <button type="submit" class="btn-action btn-approve" title="Approve">
                                                        <i class="bi bi-check-lg"></i> Approve
                                                        </button>
                                                    </form>
                                                    <!-- Reject form -->
                                                    <form method="POST" action="admin_dashboard.php" style="display: inline;">
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="hidden" name="leave_id" value="<?= $row['id'] ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                        <button type="submit" class="btn-action btn-reject" title="Reject">
                                                        <i class="bi bi-x-lg"></i> Reject
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php $box = ob_get_clean();
                                if ($row['status'] === 'pending') $pendingBoxes .= $box;
                                else $historyBoxes .= $box;
                            endwhile; ?>
                            <?= $pendingBoxes ?>
                        </div>
                    </div>
                    <div id="history-leaves" style="display: none;">
                        <div class="row">
                            <?= $historyBoxes ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Leave Types Section -->
        <div class="section" id="leave-types" style="display: none;">
            <h3 class="section-title"><i class="bi bi-card-list"></i> Leave Types Management</h3>
            
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Leave Types</h5>
                    <button class="btn btn-primary" id="openAddLeaveTypeModalBtn">
                        <i class="bi bi-plus-lg me-1"></i> Add Leave Type
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Leave Type</th>
                                    <th>Days</th>
                                    <th>Description & Policy</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $leave_types_result = $conn->query('SELECT * FROM leave_types ORDER BY name'); while($lt = $leave_types_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($lt['name']) ?></td>
                                    <td><?= htmlspecialchars($lt['days']) ?></td>
                                    <td><?= htmlspecialchars($lt['description']) ?></td>
                                    <td>
                                        <!-- Delete leave type form -->
                                        <form method="POST" action="admin_dashboard.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this leave type?');">
                                            <input type="hidden" name="action" value="delete_leave_type">
                                            <input type="hidden" name="leave_type_id" value="<?= $lt['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                            <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Leave Type Modal -->
    <div class="modal fade" id="addLeaveTypeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Leave Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addLeaveTypeForm" method="POST" action="add_leave_type.php">
                        <div class="mb-3">
                            <label class="form-label">Leave Type Name</label>
                            <input type="text" name="leave_type_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Number of Days</label>
                            <input type="number" name="leave_type_days" class="form-control" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description & Policy</label>
                            <textarea name="leave_type_description" class="form-control" rows="3" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="addLeaveTypeForm" class="btn btn-primary">Save Leave Type</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Employee Modal -->
    <div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="" method="POST" enctype="multipart/form-data" id="employeeForm">
                        <input type="hidden" name="action" value="add_employee">
                        <input type="hidden" name="id" id="employeeId">
                        
                        <div class="row">
                            <div class="col-md-4 mb-4 text-center">
                                <div class="profile-picture-placeholder" id="profilePicturePlaceholder" onclick="document.getElementById('profilePictureInput').click()">
                                    <i class="bi bi-person-plus"></i>
                                </div>
                                <input type="file" name="profile_picture" accept="image/*" id="profilePictureInput" class="d-none">
                                <small class="text-muted d-block mt-2">Click to upload profile picture</small>
                            </div>
                            <div class="col-md-8">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Employee ID</label>
                                        <input type="text" name="employee_id" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" name="name" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control" required>
                                        <div class="invalid-feedback">This email is already registered.</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Contact Number</label>
                                        <input type="tel" name="contact_number" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Job Title</label>
                                        <select name="job_title" class="form-select" required>
                                            <option value="">Select Job Title</option>
                                            <option value="Software Engineer">Software Engineer</option>
                                            <option value="Project Manager">Project Manager</option>
                                            <option value="HR Manager">HR Manager</option>
                                            <option value="Accountant">Accountant</option>
                                            <option value="Marketing Specialist">Marketing Specialist</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Department</label>
                                        <select name="department" class="form-select" required>
                                            <option value="">Select Department</option>
                                            <option value="IT">IT</option>
                                            <option value="HR">HR</option>
                                            <option value="Finance">Finance</option>
                                            <option value="Marketing">Marketing</option>
                                            <option value="Operations">Operations</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3" id="passwordField">
                                        <label class="form-label">Password</label>
                                        <input type="password" name="password" class="form-control">
                                    </div>
                                    <div class="col-md-6 mb-3" id="confirmPasswordField">
                                        <label class="form-label">Confirm Password</label>
                                        <input type="password" name="confirm_password" class="form-control">
                                        <div class="invalid-feedback">Passwords do not match.</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Birthday</label>
                                        <input type="date" name="birthday" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Gender</label>
                                        <select name="gender" class="form-select" required>
                                            <option value="">Select Gender</option>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Date of Joining</label>
                                        <input type="date" name="date_of_joining" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Role</label>
                                        <select name="role" class="form-select" required>
                                            <option value="employee">Employee</option>
                                            <option value="admin">Admin</option>
                                        </select>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Address</label>
                                        <textarea name="address" class="form-control" rows="2" required></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="employeeForm" class="btn btn-primary">Save Employee</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reason Popup Modal -->
    <div class="modal fade" id="reasonPopup" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Leave Request Reason</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="reasonPopupText"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Search Results Modal -->
    <div class="modal fade" id="searchResultsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Employee Search Results</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="searchResultsContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printSearchResults()">
                        <i class="bi bi-printer me-1"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
        
        // Navigation functionality
        function showSection(sectionId) {
            // Hide all sections
            document.querySelectorAll('.section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Show the requested section
            document.getElementById(sectionId).style.display = 'block';
            
            // Update active class on sidebar links
            document.querySelectorAll('.sidebar .nav-link').forEach(link => {
                link.classList.remove('active');
            });
            document.querySelector(`.sidebar .nav-link[href="#${sectionId}"]`).classList.add('active');
            
            // If navigating to request list, ensure a tab is active
            if (sectionId === 'request-list') {
                showTab('pending');
            }
        }
        
        // Show default section on load
        window.addEventListener('load', () => {
            const section = window.location.hash.substring(1) || 'dashboard';
            showSection(section);
        });
        
        // Handle hash changes
        window.addEventListener('hashchange', () => {
            const section = window.location.hash.substring(1) || 'dashboard';
            showSection(section);
        });
        
        // Tab functionality for request list
        function showTab(tab) {
            document.getElementById('pending-leaves').style.display = tab === 'pending' ? 'block' : 'none';
            document.getElementById('history-leaves').style.display = tab === 'history' ? 'block' : 'none';
            
            // Update active class on tab buttons
            document.getElementById('pendingTab').classList.toggle('active', tab === 'pending');
            document.getElementById('historyTab').classList.toggle('active', tab === 'history');
        }
        
        // Employee modal functions
        function showAddEmployeeModal() {
            const modal = new bootstrap.Modal(document.getElementById('addEmployeeModal'));
            const form = document.getElementById('employeeForm');
            
            // Reset form for adding
            form.reset();
            form.querySelector('input[name="action"]').value = 'add_employee';
            document.querySelector('.modal-title').textContent = 'Add New Employee';
            document.querySelector('button[type="submit"]').textContent = 'Add Employee';
            
            // Show employee ID field and password fields
            document.querySelector('input[name="employee_id"]').parentNode.style.display = 'block';
            document.getElementById('passwordField').style.display = 'block';
            document.getElementById('confirmPasswordField').style.display = 'block';
            
            // Clear any validation classes
            document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            
            // Reset profile picture preview
            const profilePicturePlaceholder = document.getElementById('profilePicturePlaceholder');
            profilePicturePlaceholder.innerHTML = '<i class="bi bi-person-plus"></i>';
            
            modal.show();
        }
        
        function openEditEmployeeModal(btn) {
            const employeeCard = btn.closest('.employee-card');
            const modal = new bootstrap.Modal(document.getElementById('addEmployeeModal'));
            const form = document.getElementById('employeeForm');
            
            // Populate form with employee data
            form.querySelector('input[name="employee_id"]').value = employeeCard.dataset.employee_id;
            form.querySelector('input[name="name"]').value = employeeCard.dataset.name;
            form.querySelector('input[name="email"]').value = employeeCard.dataset.email;
            form.querySelector('select[name="job_title"]').value = employeeCard.dataset.job_title;
            form.querySelector('input[name="contact_number"]').value = employeeCard.dataset.contact_number;
            form.querySelector('input[name="birthday"]').value = employeeCard.dataset.birthday;
            form.querySelector('select[name="department"]').value = employeeCard.dataset.department;
            form.querySelector('select[name="gender"]').value = employeeCard.dataset.gender;
            form.querySelector('input[name="date_of_joining"]').value = employeeCard.dataset.date_of_joining;
            form.querySelector('textarea[name="address"]').value = employeeCard.dataset.address;
            form.querySelector('select[name="role"]').value = employeeCard.dataset.role;
            form.querySelector('input[name="id"]').value = employeeCard.dataset.id;
            
            // Set profile picture preview
            const profilePicturePlaceholder = document.getElementById('profilePicturePlaceholder');
            const profilePicturePath = employeeCard.dataset.profile_picture;
            if (profilePicturePath && profilePicturePath !== 'default-avatar.png') {
                profilePicturePlaceholder.innerHTML = `<img src="${profilePicturePath}" alt="Profile Picture">`;
            } else {
                profilePicturePlaceholder.innerHTML = '<i class="bi bi-person"></i>';
            }
            
            // Change form for editing
            form.querySelector('input[name="action"]').value = 'edit_employee';
            document.querySelector('.modal-title').textContent = 'Edit Employee';
            document.querySelector('button[type="submit"]').textContent = 'Save Changes';
            
            // Hide employee ID field and password fields
            document.querySelector('input[name="employee_id"]').parentNode.style.display = 'none';
            document.getElementById('passwordField').style.display = 'none';
            document.getElementById('confirmPasswordField').style.display = 'none';
            
            modal.show();
        }
        
        // Profile picture preview
        document.getElementById('profilePictureInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const profilePicturePlaceholder = document.getElementById('profilePicturePlaceholder');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    profilePicturePlaceholder.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Leave type modal
        document.getElementById('openAddLeaveTypeModalBtn').addEventListener('click', function() {
            const modal = new bootstrap.Modal(document.getElementById('addLeaveTypeModal'));
            modal.show();
        });
        
        // Reason popup
        function showReasonPopup(text) {
            document.getElementById('reasonPopupText').textContent = text;
            const modal = new bootstrap.Modal(document.getElementById('reasonPopup'));
            modal.show();
        }
        
        // Filter functions
        function resetFilters() {
            document.getElementById('status_filter').value = '';
            document.getElementById('date_from').value = '';
            document.getElementById('date_to').value = '';
            document.getElementById('employee_filter').value = '';
            document.getElementById('leave_type_filter').value = '';
            document.getElementById('filterForm').submit();
        }
        
        function resetEmployeeFilters() {
            document.getElementById('department_filter').value = '';
            document.getElementById('employeeFilterForm').submit();
        }
        
        function exportToCSV() {
            window.location.href = 'export_leave_data.php?format=csv';
        }
        
        function exportEmployeeData() {
            const departmentFilter = document.getElementById('department_filter').value;
            window.location.href = `export_employee_data.php?format=csv&department_filter=${departmentFilter}`;
        }
        
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Search functionality
        function performSearch() {
            const searchTerm = document.getElementById('globalSearch').value.trim();
            if (!searchTerm) return;

            // Show loading state
            const searchResultsContent = document.getElementById('searchResultsContent');
            searchResultsContent.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';

            // Fetch search results
            fetch(`search_employee.php?term=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        searchResultsContent.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        return;
                    }

                    let html = '';
                    data.forEach(employee => {
                        html += `
                            <div class="card mb-3 shadow-sm border-0">
                                <div class="card-header bg-white py-3 border-bottom-0">
                                    <div class="d-flex align-items-center">
                                        <img src="${employee.profile_picture && employee.profile_picture !== 'default-avatar.png' ? employee.profile_picture : 'uploads/default-avatar.png'}" 
                                             alt="Profile Picture" class="me-3 shadow-sm" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;">
                                        <div>
                                            <h4 class="mb-0 text-primary">${employee.name} <small class="text-muted">(${employee.employee_id})</small></h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body pt-0">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <h6 class="text-secondary mb-2">Employee Information</h6>
                                            <p class="mb-1"><strong>Department:</strong> ${employee.department}</p>
                                            <p class="mb-1"><strong>Job Title:</strong> ${employee.job_title}</p>
                                            <p class="mb-1"><strong>Email:</strong> ${employee.email}</p>
                                            <p class="mb-1"><strong>Contact:</strong> ${employee.contact_number}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="text-secondary mb-2">Leave Statistics</h6>
                                            <p class="mb-1"><strong>Total Leaves:</strong> ${employee.total_leaves}</p>
                                            <p class="mb-1"><strong>Approved:</strong> ${employee.approved_leaves}</p>
                                            <p class="mb-1"><strong>Pending:</strong> ${employee.pending_leaves}</p>
                                            <p class="mb-1"><strong>Rejected:</strong> ${employee.rejected_leaves}</p>
                                        </div>
                                    </div>
                                    <hr class="my-3">
                                    <div>
                                        <h6 class="text-secondary mb-2">Recent Leave History</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover mb-0">
                                                <thead>
                                                    <tr class="table-light">
                                                        <th>Type</th>
                                                        <th>Start Date</th>
                                                        <th>End Date</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${employee.recent_leaves.map(leave => `
                                                        <tr>
                                                            <td>${leave.type}</td>
                                                            <td>${leave.start_date}</td>
                                                            <td>${leave.end_date}</td>
                                                            <td><span class="badge bg-${leave.status === 'approved' ? 'success' : leave.status === 'pending' ? 'warning' : 'danger'}">${leave.status}</span></td>
                                                        </tr>
                                                    `).join('')}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    searchResultsContent.innerHTML = html || '<div class="alert alert-info">No results found</div>';
                })
                .catch(error => {
                    searchResultsContent.innerHTML = '<div class="alert alert-danger">Error performing search</div>';
                    console.error('Search error:', error);
                });

            // Show the modal
            const searchModal = new bootstrap.Modal(document.getElementById('searchResultsModal'));
            searchModal.show();
        }

        // Handle Enter key in search input
        document.getElementById('globalSearch').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });

        function printSearchResults() {
            const content = document.getElementById('searchResultsContent').innerHTML;
            const printWindow = window.open('', '_blank');

            let printContentHtml = '';
            document.querySelectorAll('#searchResultsContent .card').forEach(card => {
                const nameHeader = card.querySelector('.card-header h4').textContent; // Updated selector
                const profilePicUrl = card.querySelector('.card-header img') ? card.querySelector('.card-header img').src : 'uploads/default-avatar.png';
                const employeeInfoDiv = card.querySelector('.card-body > .row > div:nth-child(1)'); 
                const leaveStatsDiv = card.querySelector('.card-body > .row > div:nth-child(2)'); 
                const leaveHistoryDiv = card.querySelector('.card-body > div:nth-child(3)'); 

                let employeeInfoHtml = '';
                if (employeeInfoDiv) {
                    employeeInfoHtml += employeeInfoDiv.innerHTML.replace('<h6 class="text-secondary mb-2">Employee Information</h6>', ''); // Get all content, remove heading
                }

                let leaveStatsHtml = '';
                if (leaveStatsDiv) {
                    leaveStatsHtml += leaveStatsDiv.innerHTML.replace('<h6 class="text-secondary mb-2">Leave Statistics</h6>', ''); // Get all content, remove heading
                }

                let leaveHistoryHtml = '';
                if (leaveHistoryDiv) {
                    leaveHistoryHtml += leaveHistoryDiv.innerHTML.replace('<h6 class="text-secondary mb-2">Recent Leave History</h6>', ''); // Get all content, remove heading
                }

                printContentHtml += `
                    <div class="print-employee-section mb-4">
                        <div class="d-flex align-items-center mb-3">
                            <img src="${profilePicUrl}" alt="Profile Picture" class="print-profile-pic me-3">
                            <div>
                                <h4 class="mb-1">${nameHeader}</h4>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-6 print-info-block">
                                <h6>Employee Information</h6>
                                ${employeeInfoHtml}
                            </div>
                            <div class="col-6 print-info-block">
                                <h6>Leave Statistics</h6>
                                ${leaveStatsHtml}
                            </div>
                        </div>

                        <div class="mt-3 print-history-block">
                            <h6>Recent Leave History</h6>
                            ${leaveHistoryHtml}
                        </div>
                    </div>
                    <div class="print-page-break"></div>
                `;
            });

            printWindow.document.write(`
                <html>
                    <head>
                        <title>Employee Search Results Report</title>
                        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
                        <style>
                            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 30px; color: #333; }
                            .print-header { text-align: center; margin-bottom: 40px; border-bottom: 2px solid #000; padding-bottom: 15px; }
                            .print-header h1 { margin: 0; color: #333; }
                            .print-employee-section { margin-bottom: 30px; padding: 20px; border: 1px solid #000; border-radius: 8px; background-color: #fff; }
                            .print-profile-pic { width: 100px; height: 100px; object-fit: cover; border: 1px solid #000; border-radius: 8px; }
                            .print-info-block, .print-history-block { padding: 15px; border: 1px solid #eee; border-radius: 5px; margin-bottom: 15px; }
                            .print-history-block table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                            .print-history-block th, .print-history-block td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                            .print-history-block th { background-color: #f2f2f2; }
                            h4, h5 { color: #555; margin-top: 0; margin-bottom: 10px; }
                            p { margin-bottom: 5px; font-size: 0.95rem; line-height: 1.4; }
                            .status-badge { padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; }
                            .badge-approved { background-color: #d4edda; color: #155724; }
                            .badge-pending { background-color: #fff3cd; color: #856404; }
                            .badge-rejected { background-color: #f8d7da; color: #721c24; }
                            .print-page-break { page-break-after: always; }
                            .print-page-break:last-child { page-break-after: avoid; }
                            @media print {
                                .no-print { display: none; }
                                body { padding: 0; margin: 0; }
                                .print-employee-section { box-shadow: none; border: 1px solid #000; }
                                .print-info-block, .print-history-block { border: 1px solid #000; }
                                .print-history-block table, .print-history-block th, .print-history-block td { border: 1px solid #000; }
                                .print-header { border-bottom-color: #000; }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="no-print mb-3">
                            <button onclick="window.print()" class="btn btn-primary">Print</button>
                            <button onclick="window.close()" class="btn btn-secondary">Close</button>
                        </div>

                        <div class="print-header">
                            <h1>Employee Search Results</h1>
                        </div>

                        ${printContentHtml}
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        function exportSearchResults() {
            const searchTerm = document.getElementById('globalSearch').value.trim();
            window.location.href = `export_search_results.php?term=${encodeURIComponent(searchTerm)}`;
        }

        // Initialize Bootstrap modals
        const searchModal = new bootstrap.Modal(document.getElementById('searchResultsModal'));
        const leaveDetailsModal = new bootstrap.Modal(document.getElementById('leaveDetailsModal'));
        const employeeDetailsModal = new bootstrap.Modal(document.getElementById('employeeDetailsModal'));

        // Global variables for confirmation
        let currentAction = null;
        let currentId = null;

        // Function to show confirmation modal
        function showConfirmation(message, action, id) {
            document.getElementById('confirmationMessage').textContent = message;
            currentAction = action;
            currentId = id;
            confirmationModal.show();
        }

        // Handle confirmation button click
        document.getElementById('confirmActionBtn').addEventListener('click', function() {
            if (currentAction === 'deleteLeave') {
                deleteLeaveRequest(currentId);
            } else if (currentAction === 'deleteEmployee') {
                deleteEmployee(currentId);
            }
            confirmationModal.hide();
        });

        // Update delete functions to use confirmation modal
        function deleteLeaveRequest(id) {
            showConfirmation('Are you sure you want to delete this leave request?', 'deleteLeave', id);
        }

        function deleteEmployee(id) {
            showConfirmation('Are you sure you want to delete this employee?', 'deleteEmployee', id);
        }

        // Update the actual delete functions
        function confirmDeleteLeave(id) {
            fetch('delete_leave.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    // Remove the row from the table
                    document.querySelector(`tr[data-leave-id="${id}"]`).remove();
                    // Show success message
                    alert('Leave request deleted successfully');
                } else {
                    alert('Error deleting leave request: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting leave request');
            });
        }

        function confirmDeleteEmployee(id) {
            fetch('delete_employee.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    // Remove the row from the table
                    document.querySelector(`tr[data-employee-id="${id}"]`).remove();
                    // Show success message
                    alert('Employee deleted successfully');
                } else {
                    alert('Error deleting employee: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting employee');
            });
        }

        function exportReport(type) {
            const month = document.getElementById('monthSelector').value;
            const year = document.getElementById('yearSelector').value;
            // Redirect to export script with CSV format
            window.location.href = `export_report.php?type=${type}&month=${month}&year=${year}&format=csv`;
        }

        function exportAllReports() {
            // Export all reports in sequence
            const types = ['trends', 'types', 'monthly'];
            types.forEach(type => exportReport(type));
        }
    </script>
    <!-- Custom Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="confirmationModalLabel">Confirm Action</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmationMessage">Are you sure you want to proceed with this action?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmActionBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize confirmation modal
        const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
        let currentAction = null;
        let currentId = null;

        // Function to show confirmation modal
        function showConfirmation(message, action, id) {
            document.getElementById('confirmationMessage').textContent = message;
            currentAction = action;
            currentId = id;
            confirmationModal.show();
        }

        // Handle confirmation button click
        document.getElementById('confirmActionBtn').addEventListener('click', function() {
            if (currentAction === 'deleteLeave') {
                confirmDeleteLeave(currentId);
            } else if (currentAction === 'deleteEmployee') {
                confirmDeleteEmployee(currentId);
            }
            confirmationModal.hide();
        });

        // Update delete functions to use confirmation modal
        function deleteLeaveRequest(id) {
            showConfirmation('Are you sure you want to delete this leave request?', 'deleteLeave', id);
        }

        function deleteEmployee(id) {
            showConfirmation('Are you sure you want to delete this employee?', 'deleteEmployee', id);
        }
    </script>
</body>
</html>