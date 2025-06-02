<?php
session_start();
include 'db.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.html');
    exit();
}

// Handle leave request actions (Approve/Reject)
if (isset($_GET['action']) && isset($_GET['leave_id'])) {
    $action = $_GET['action'];
    $leave_id = $_GET['leave_id'];

    if ($action === 'approve' || $action === 'reject') {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = $conn->prepare('UPDATE leaves SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $status, $leave_id);
        $stmt->execute();
    }
    // Redirect back to the requests list after action
    header('Location: admin_dashboard.php#request-list');
    exit();
}

// Handle delete leave type action
if (isset($_GET['action']) && $_GET['action'] === 'delete_leave_type' && isset($_GET['leave_type_id'])) {
    $leave_type_id = $_GET['leave_type_id'];

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
<html>
<head>
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .leave-boxes { display: flex; flex-wrap: wrap; gap: 18px; }
    .leave-box {
      border: 1.5px solid #bbb;
      border-radius: 8px;
      padding: 18px 20px 38px 20px;
      background: #fff;
      min-width: 260px;
      max-width: 320px;
      flex: 1 1 260px;
      cursor: pointer;
      position: relative;
      transition: box-shadow 0.2s, border 0.2s;
    }
    .leave-box:hover { box-shadow: 0 4px 16px #0001; border: 1.5px solid #111; }
    .leave-box .actions { position: absolute; right: 18px; bottom: 14px; display: flex; gap: 8px; }
    .leave-box .actions a {
      background: #111;
      color: #fff !important;
      padding: 4px 14px;
      border-radius: 4px;
      font-size: 14px;
      text-decoration: none;
      font-weight: 600;
      transition: background 0.2s;
      cursor: pointer;
    }
    .leave-box .actions a:hover { background: #333; }
    .leave-box .status {
      display: inline-block;
      padding: 2px 10px;
      border-radius: 12px;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 6px;
    }
    .status.pending { background: #fffbe6; color: #b59f00; border: 1px solid #b59f00; }
    .status.approved { background: #e6ffed; color: #1a7f37; border: 1px solid #1a7f37; }
    .status.rejected { background: #ffeaea; color: #c00; border: 1px solid #c00; }
    .popup-bg {
      display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
      background: rgba(0,0,0,0.25); z-index: 1000; align-items: center; justify-content: center;
    }
    .popup-bg.active { display: flex; }
    .popup {
      background: #fff; border-radius: 10px; padding: 32px 28px; min-width: 320px; max-width: 95vw;
      box-shadow: 0 8px 32px #0002;
      position: relative;
    }
    .popup h3 { margin-top: 0; }
    .popup .close-btn {
      position: absolute; top: 10px; right: 18px; font-size: 22px; color: #888; cursor: pointer;
      background: none; border: none;
    }
    .popup table { margin: 0; }
    .tab-btns { display: flex; gap: 18px; margin-bottom: 24px; }
    .tab-btn { background: #fff; color: #111; border: 1.5px solid #bbb; border-radius: 6px; padding: 8px 32px; font-size: 1.1em; font-weight: 600; cursor: pointer; transition: background 0.2s, color 0.2s, border 0.2s; }
    .tab-btn.active, .tab-btn:focus { background: #111; color: #fff; border: 1.5px solid #111; }
    .employee-boxes {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      margin-top: 20px;
    }
    
    .employee-box {
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 0;
      width: 250px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      overflow: hidden;
      position: relative;
    }
    
    .employee-box .top-section {
      background: linear-gradient(to right, #6a82fb, #fc5c7d);
      height: 70px;
      position: relative;
      margin-bottom: 35px;
    }
    
    .employee-box .image-container {
      width: 80px; /* Should match image size */
      height: 80px; /* Should match image size */
      border-radius: 50%;
      position: absolute;
      bottom: -40px; /* Should match image bottom */
      left: 50%;
      transform: translateX(-50%);
      background-color: #fff; /* White background */
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      border: 3px solid #fff;
      box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }

    .employee-box img {
      display: block;
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 50%; /* Ensure image is also rounded */
    }
    
    .employee-box .details {
      text-align: center;
      padding: 8px 15px 15px 15px;
    }
    
    .employee-box h3 {
      margin: 0 0 4px 0;
      color: #333;
      font-size: 1em;
    }
    
    .employee-box p {
      margin: 4px 0; 
      color: #666;
      font-size: 0.9em;
      padding-bottom: 6px; 
      border-bottom: 1px solid #ccc;
    }
    
    .employee-box .details p:nth-child(-n+2) {
        padding-bottom: 6px; 
        border-bottom: 1px solid #ccc; 
    }

    .employee-box p:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }
    
    .employee-box .edit-icon {
      position: absolute;
      top: 5px; /* Adjusted position */
      right: 5px; /* Adjusted position */
      background: #111; /* Black background */
      border-radius: 50%;
      width: 20px; /* Adjusted size */
      height: 20px; /* Adjusted size */
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1); /* Adjusted shadow */
      cursor: pointer;
    }
    
    .employee-box .edit-icon span {
      font-size: 12px; /* Adjusted font size */
      color: #fff; /* White color for the icon */
    }
    
    .add-employee-btn {
      background: #333; /* Dark background */
      color: white;
      padding: 8px 16px; /* Reduced padding */
      border: none;
      border-radius: 4px;
      cursor: pointer;
      margin-bottom: 20px;
      transition: background 0.2s ease;
      display: block;
      width: fit-content; /* Set width to fit content */
      text-align: right; /* Align text to the right */
    }
    
    .add-employee-btn:hover {
      background: #555; /* Slightly lighter dark for hover */
    }
    
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 1000;
    }
    
    .modal-content {
      background: white;
      width: 95%;
      max-width: 1200px;
      margin: 20px auto;
      padding: 20px;
      border-radius: 8px;
      max-height: 95vh;
      overflow-y: auto;
      display: flex;
      gap: 20px;
      position: relative;
      padding-top: 30px;
    }
    
    .modal .close-btn {
      position: absolute;
      top: 1px !important;
      right: 15px !important;
      font-size: 22px;
      color: #888;
      cursor: pointer;
      background: none;
      border: none;
      padding: 5px;
      z-index: 1001;
      width: fit-content;
      height: fit-content;
    }

    .modal .close-btn:hover {
      color: #555;
    }
    
    .form-section {
      flex: 1;
      display: flex;
      flex-direction: column;
    }
    
    .profile-picture-section {
      width: 200px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 10px;
      margin-top: 30px;
      position: sticky;
      top: 30px;
    }
    
    .profile-picture-placeholder {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      background: #eee;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 60px;
      color: #aaa;
      overflow: hidden;
    }

    .profile-picture-placeholder img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .profile-picture-section input[type="file"] {
      display: block;
      width: 120px; 
      padding: 8px 12px;
      border: none;
      border-radius: 20px; 
      background: linear-gradient(to right, #6a82fb, #fc5c7d); 
      color: white; 
      cursor: pointer;
      text-align: center;
      font-size: 0.9em;
      transition: background 0.3s ease;
    }

    .profile-picture-section input[type="file"]:hover {
        background: linear-gradient(to right, #5a71e1, #e84c6d);
    }

     .profile-picture-section input[type="file"]::file-selector-button {
        display: none;
     }
    
    .form-row {
      display: flex;
      gap: 10px;
      margin-bottom: 10px;
    }
    
    .form-group {
      flex: 1;
      margin-bottom: 0;
      position: relative;
    }
    
    .form-group.full-width {
      width: 100%;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 4px;
      font-size: 0.9em;
      color: #555;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 6px 8px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 0.95em;
      box-sizing: border-box; /* Include padding and border in element's total width and height */
      vertical-align: top; /* Align elements to the top */
    }
    
    .form-group textarea {
      height: 60px;
      resize: vertical;
    }
    
    .form-actions {
      margin-top: 20px;
      text-align: right;
    }
    
    .form-actions button {
      margin-left: 10px;
    }

    /* Sidebar Styles */
    .dashboard-container {
        display: flex;
        height: 100vh; /* Full viewport height */
        width: 100%;
    }

    .sidebar {
        width: 200px; /* Fixed width */
        background-color: #f0f0f0; /* Light grey background */
        padding: 20px;
        box-shadow: 2px 0 5px rgba(0,0,0,0.1); /* Subtle shadow on the right */
        display: flex;
        flex-direction: column;
        gap: 10px;
        border-right: 1px solid black; /* Added black border on the right */
    }

    .sidebar a {
        display: block;
        padding: 10px;
        background-color: #fff; /* White background for links */
        border: 1px solid #ddd; /* Added border */
        border-radius: 4px;
        text-decoration: none;
        color: #333;
        text-align: center;
        transition: background-color 0.2s ease, border-color 0.2s ease; /* Added border-color to transition */
    }

    .sidebar a:hover {
        background-color: #eee; /* Slightly darker on hover */
        border-color: #ccc; /* Darker border on hover */
    }

    .sidebar a.active {
        background-color: #111; /* Black background for active */
        color: #fff; /* White text for active */
        border-color: #111; /* Dark border for active */
    }

    .main-content {
        flex: 1; /* Take remaining width */
        padding: 20px;
        overflow-y: auto; /* Add scrolling if content exceeds height */
        width: calc(100% - 200px); /* Adjust width based on sidebar */
    }

    .password-input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }
    
    .password-input-wrapper input {
      padding-right: 30px;
    }
    
    .error-icon {
      position: absolute;
      right: 8px;
      color: #dc3545;
      display: none;
      font-size: 16px;
    }
    
    .error-icon.show {
      display: block;
    }

    .profile-picture-placeholder {
        border: 2px solid #ddd;
        transition: border-color 0.3s ease;
    }

    .profile-picture-placeholder.error {
        border: 2px solid red;
    }

  </style>
  
</head>
<body>
  <div class="dashboard-container">
    <div class="sidebar">
      <!-- Sidebar content here -->
      <a href="#dashboard" class="active">Dashboard</a>
      <a href="#employee-list">Employee List</a>
      <a href="#request-list">Leave Requests</a>
      <a href="#leave-types" id="leaveTypesSidebarBtn">Leave Types</a>
      <a href="calendar_view.php">Calendar View</a>
       <a href="reports.php">Reports & Analytics</a>
    </div>
    
    <div class="main-content">
      <div style="text-align:right; margin-bottom: 20px;"><a href="logout.php" class="logout-link">Logout</a></div>

      <!-- MOVE Leave Type box HERE -->
      <div class="dashboard-box" id="leave-types" style="display:none; max-width:900px; margin:40px auto 0 auto; background:none; padding:0; border:none; border-radius:0; box-shadow:none;">
        <div style="display: flex; align-items: center; gap: 18px; margin-bottom: 18px;">
          <h2 style="margin-bottom: 0; color:#111; letter-spacing:1px; font-size:2rem; font-weight:700;">Leave Types</h2>
          <button id="openAddLeaveTypeModalBtn" class="add-employee-btn" style="padding: 8px 16px; font-size: 1em; margin: 0; background: #333; color: #fff; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; justify-content: center; text-align: center;">Add</button>
        </div>
        <table style="width: 100%; background: #fff; border-radius: 8px; overflow: hidden; table-layout: fixed;">
          <thead style="background: #1976d2; color: #fff;">
            <tr>
              <th style="width: 30%;">Leave Type</th>
              <th style="width: 15%;">Days</th>
              <th style="width: 40%;">Description & Policy</th>
              <th style="width: 15%;">Actions</th>
            </tr>
          </thead>
          <tbody id="leaveTypesTableBody">
            <?php $leave_types_result = $conn->query('SELECT * FROM leave_types ORDER BY name'); while($lt = $leave_types_result->fetch_assoc()): ?>
            <tr style="height: auto; min-height: 44px;">
              <td><?= htmlspecialchars($lt['name']) ?></td>
              <td><?= htmlspecialchars($lt['days']) ?></td>
              <td style="white-space: pre-wrap;"><?= htmlspecialchars($lt['description']) ?></td>
              <td><a href="?action=delete_leave_type&leave_type_id=<?= $lt['id'] ?>" onclick="return confirm('Are you sure you want to delete this leave type?');" style="color: red; text-decoration: none;">Delete</a></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        <!-- Add Leave Type Modal -->
        <div id="addLeaveTypeModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); z-index:2000; align-items:center; justify-content:center;">
          <div style="background:#fff; border:none; border-radius:10px; padding:32px 28px; min-width:320px; max-width:95vw; box-shadow:0 8px 32px #0002; position:relative;">
            <button id="closeAddLeaveTypeModalBtn" style="position:absolute; top:10px; right:18px; font-size:22px; color:#888; cursor:pointer; background:none; border:none; padding:5px; z-index:1001; width:fit-content; height:fit-content;">&times;</button>
            <h3 style="margin-top:0; color:#111;">Add Leave Type</h3>
            <form id="addLeaveTypeForm" method="POST" action="add_leave_type.php" style="display:flex; flex-direction:column; gap:10px;">
              <label for="leave_type_name">Leave Type:</label>
              <input type="text" name="leave_type_name" id="leave_type_name" required>
              <label for="leave_type_days">Days:</label>
              <input type="number" name="leave_type_days" id="leave_type_days" min="1" required>
              
              <!-- Add Description Field Here -->
              <label for="leave_type_description">Description & Policy:</label>
              <textarea name="leave_type_description" id="leave_type_description" required
                        placeholder="Enter detailed description and policy for this leave type."
                        style="width: 100%; resize: vertical; min-height: 80px;"></textarea>
              
              <div style="display:flex; gap:16px; margin-top:18px; justify-content:center;">
                <button type="submit" style="background:#333; color:#fff; border:none; border-radius:4px; padding:8px 16px; font-weight:600; font-size:1em; cursor:pointer;">Save</button>
                <button type="button" id="cancelAddLeaveTypeModalBtn" style="background:#333; color:#fff; border:none; border-radius:4px; padding:8px 16px; font-weight:600; font-size:1em; cursor:pointer;">Cancel</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="dashboard-box" id="dashboard">
        <h2>Dashboard</h2>
        <div style="display: flex; gap: 20px; margin-bottom: 30px; justify-content: center;">
          <div style="background: linear-gradient(135deg, #222 0%, #111 100%); border-radius: 8px; padding: 20px; min-width: 180px; text-align: center; color: #fff;">
            <div style="font-size: 2.5em; font-weight: bold; color: #fff;"><?= $leave_types_count ?></div>
            <div>Leave Types</div>
          </div>
          <div style="background: linear-gradient(135deg, #222 0%, #111 100%); border-radius: 8px; padding: 20px; min-width: 180px; text-align: center; color: #fff;">
            <div style="font-size: 2.5em; font-weight: bold; color: #fff;"><?= $employees_count ?></div>
            <div>Employees</div>
          </div>
          <div style="background: linear-gradient(135deg, #222 0%, #111 100%); border-radius: 8px; padding: 20px; min-width: 180px; text-align: center; color: #fff;">
            <div style="font-size: 2.5em; font-weight: bold; color: #fff;"><?= $approved_count ?></div>
            <div>Approved</div>
          </div>
          <div style="background: linear-gradient(135deg, #222 0%, #111 100%); border-radius: 8px; padding: 20px; min-width: 180px; text-align: center; color: #fff;">
            <div style="font-size: 2.5em; font-weight: bold; color: #fff;"><?= $rejected_count ?></div>
            <div>Rejected</div>
          </div>
          <div style="background: linear-gradient(135deg, #222 0%, #111 100%); border-radius: 8px; padding: 20px; min-width: 180px; text-align: center; color: #fff;">
            <div style="font-size: 2.5em; font-weight: bold; color: #fff;"><?= $pending_count ?></div>
            <div>Pending</div>
          </div>
        </div>
        <h3>Recent request history</h3>
        <div style="display: flex; justify-content: center; width: 100%;"><table style="width: 900px; background: #fff; border-radius: 8px; overflow: hidden; table-layout: fixed; margin: 0 auto;">
          <thead style="background: #1976d2; color: #fff;">
            <tr>
              <th style="width: 120px;">Request Date</th>
              <th style="width: 120px;">Employee</th>
              <th style="width: 120px;">Leave Type</th>
              <th style="width: 100px;">From</th>
              <th style="width: 100px;">To</th>
              <th style="width: 220px;">Reason</th>
              <th style="width: 100px;">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php while($row = $recent_requests->fetch_assoc()): ?>
              <tr style="height: 44px;">
                <td><?= htmlspecialchars(date('d-m-Y', strtotime($row['applied_at']))) ?></td>
                <td><?= htmlspecialchars($row['employee']) ?></td>
                <td><?= htmlspecialchars($row['leave_type']) ?></td>
                <td><?= htmlspecialchars(date('d-m-Y', strtotime($row['start_date']))) ?></td>
                <td><?= htmlspecialchars(date('d-m-Y', strtotime($row['end_date']))) ?></td>
                <td style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor:pointer;" onclick="showReasonPopup(this)">
                  <?= htmlspecialchars($row['purpose']) ?>
                </td>
                <td>
                  <?php
                    $status = strtolower($row['status']);
                    $color = $status === 'approved' ? '#e6ffed' : ($status === 'rejected' ? '#ffeaea' : '#e3e3e3');
                    $text = $status === 'approved' ? '#1a7f37' : ($status === 'rejected' ? '#c00' : '#b59f00');
                  ?>
                  <span style="background:<?= $color ?>;color:<?= $text ?>;padding:2px 10px;border-radius:12px;">
                    <?= ucfirst($status) ?>
                  </span>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table></div>
      </div>

      <div class="dashboard-box" id="employee-list">
        <div style="display: flex; align-items: center; gap: 18px; margin-bottom: 18px;">
          <h2 style="margin-bottom: 0; color:#111; letter-spacing:1px; font-size:2rem; font-weight:700;">Employee List</h2>
          <button class="add-employee-btn" onclick="showAddEmployeeModal()" style="padding: 8px 16px; font-size: 1em; margin: 0; min-width: 80px; display: flex; align-items: center; justify-content: center; text-align: center;">Add</button>
        </div>
        
        <div class="employee-boxes">
          <?php while($employee = $employees_result->fetch_assoc()): ?>
            <div class="employee-box"
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
              data-profile_picture="<?= htmlspecialchars($employee['profile_picture'] ?? '') ?>"
            >
              <div class="top-section">
                <div class="image-container">
                  <?php if (!empty($employee['profile_picture']) && file_exists($employee['profile_picture'])): ?>
                    <img src="<?= htmlspecialchars($employee['profile_picture']) ?>" alt="Profile Picture">
                  <?php else: ?>
                    <div style="width: 100%; height: 100%; background: #fff; display: flex; align-items: center; justify-content: center; font-size: 40px; color: #ccc;">&#128100;</div>
                  <?php endif; ?>
                </div>
                <div class="edit-icon" onclick="openEditEmployeeModal(this)"><span>&#9998;</span></div>
              </div>
              <div class="details">
                <h3><?= htmlspecialchars($employee['name']) ?></h3>
                <p><?= htmlspecialchars($employee['job_title']) ?></p>
                <p style="margin-bottom: 0; border-bottom: none; padding-bottom: 0;"><?= htmlspecialchars($employee['email']) ?></p>
                <p style="margin-top: 0; border-bottom: none; padding-bottom: 0;"><?= htmlspecialchars($employee['contact_number']) ?></p>
              </div>
            </div>
          <?php endwhile; ?>
        </div>
      </div>

      <div class="dashboard-box" id="request-list">
        <h2>All Employee Leaves</h2>
        
        <!-- Add Filter Section -->
        <div class="filter-section" style="margin-bottom: 20px; background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <form id="filterForm" method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label for="status_filter">Status:</label>
                    <select name="status_filter" id="status_filter" class="filter-input">
                        <option value="">All Status</option>
                        <option value="pending" <?= isset($_GET['status_filter']) && $_GET['status_filter'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= isset($_GET['status_filter']) && $_GET['status_filter'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= isset($_GET['status_filter']) && $_GET['status_filter'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label for="date_from">From Date:</label>
                    <input type="date" name="date_from" id="date_from" class="filter-input" value="<?= isset($_GET['date_from']) ? $_GET['date_from'] : '' ?>">
                </div>
                
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label for="date_to">To Date:</label>
                    <input type="date" name="date_to" id="date_to" class="filter-input" value="<?= isset($_GET['date_to']) ? $_GET['date_to'] : '' ?>">
                </div>
                
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label for="employee_filter">Employee:</label>
                    <select name="employee_filter" id="employee_filter" class="filter-input">
                        <option value="">All Employees</option>
                        <?php
                        $employees = $conn->query('SELECT id, name FROM users WHERE role = "employee" ORDER BY name');
                        while($emp = $employees->fetch_assoc()):
                        ?>
                        <option value="<?= $emp['id'] ?>" <?= isset($_GET['employee_filter']) && $_GET['employee_filter'] == $emp['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($emp['name']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label for="leave_type_filter">Leave Type:</label>
                    <select name="leave_type_filter" id="leave_type_filter" class="filter-input">
                        <option value="">All Types</option>
                        <?php
                        $leave_types = $conn->query('SELECT id, name FROM leave_types ORDER BY name');
                        while($lt = $leave_types->fetch_assoc()):
                        ?>
                        <option value="<?= $lt['id'] ?>" <?= isset($_GET['leave_type_filter']) && $_GET['leave_type_filter'] == $lt['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($lt['name']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-actions" style="display: flex; gap: 10px;">
                    <button type="submit" class="filter-btn" style="background: #333; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Apply Filters</button>
                    <button type="button" onclick="resetFilters()" class="filter-btn" style="background: #666; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Reset</button>
                    <button type="button" onclick="exportToCSV()" class="filter-btn" style="background: #28a745; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Export CSV</button>
                    <button type="button" onclick="exportToPDF()" class="filter-btn" style="background: #dc3545; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Export PDF</button>
                </div>
            </form>
        </div>

        <div class="tab-btns">
          <button class="tab-btn active" id="pendingTab" onclick="showTab('pending')">Pending</button>
          <button class="tab-btn" id="historyTab" onclick="showTab('history')">History</button>
        </div>
        <div class="leave-boxes" id="pending-leaves">
          <?php $leaves = [];
          $pendingBoxes = '';
          $historyBoxes = '';
          while($row = $result->fetch_assoc()):
            $leaves[$row['user_id']][] = $row;
            ob_start(); ?>
            <div class="leave-box" data-userid="<?= $row['user_id'] ?>" data-leaveid="<?= $row['id'] ?>" onclick="showHistory(<?= $row['user_id'] ?>, event)" data-status="<?= $row['status'] ?>">
              <div style="font-weight:700; font-size:1.1em; margin-bottom:2px;"> <?= htmlspecialchars($row['name']) ?> </div>
              <div style="font-size:0.98em; margin-bottom:4px;"> <?= htmlspecialchars($row['leave_type']) ?> </div>
              <div class="status <?= htmlspecialchars($row['status']) ?>"> <?= htmlspecialchars(ucfirst($row['status'])) ?> </div>
              <div style="font-size:0.95em; margin-bottom:22px;"> <?= htmlspecialchars($row['start_date']) ?> to <?= htmlspecialchars($row['end_date']) ?> (<?= htmlspecialchars($row['duration']) ?> days)</div>
              <div class="actions" onclick="event.stopPropagation();">
                <?php if($row['status'] === 'pending'): ?>
                  <a href="?action=approve&leave_id=<?= $row['id'] ?>">Approve</a>
                  <a href="?action=reject&leave_id=<?= $row['id'] ?>">Reject</a>
                <?php else: ?>
                  <span style="color:#888; font-size:13px;">-</span>
                <?php endif; ?>
              </div>
            </div>
          <?php $box = ob_get_clean();
            if ($row['status'] === 'pending') $pendingBoxes .= $box;
            else $historyBoxes .= $box;
          endwhile; ?>
          <?= $pendingBoxes ?>
        </div>
        <div class="leave-boxes" id="history-leaves" style="display:none;">
          <?= $historyBoxes ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Add Employee Modal -->
  <div id="addEmployeeModal" class="modal">
    <div class="modal-content">
      <button class="close-btn" onclick="hideAddEmployeeModal()" aria-label="Close">&times;</button>
      <div class="form-section">
        <h2>Add New Employee</h2>
        <form action="" method="POST" enctype="multipart/form-data" style="display: flex; gap: 20px;">
          <div style="flex: 1;">
            <input type="hidden" name="action" value="add_employee">
            
            <div class="form-row">
              <div class="form-group">
                <label>Employee ID</label>
                <input type="text" name="employee_id" id="employee_id" autocomplete="off" required>
              </div>
              
              <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" id="name" autocomplete="name" required>
              </div>
              
              <div class="form-group">
                <label>Job Title</label>
                <select name="job_title" id="job_title" autocomplete="organization-title" required>
                  <option value="">Select Job Title</option>
                  <option value="Software Engineer">Software Engineer</option>
                  <option value="Project Manager">Project Manager</option>
                  <option value="HR Manager">HR Manager</option>
                  <option value="Accountant">Accountant</option>
                  <option value="Marketing Specialist">Marketing Specialist</option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="email" autocomplete="email" required>
              </div>
              
              <div class="form-group">
                <label>Contact Number</label>
                <input type="tel" name="contact_number" id="contact_number" autocomplete="tel" required>
              </div>
              
              <div class="form-group">
                <label>Birthday</label>
                <input type="date" name="birthday" id="birthday" autocomplete="bday" required>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Department</label>
                <select name="department" id="department" autocomplete="organization" required>
                  <option value="">Select Department</option>
                  <option value="IT">IT</option>
                  <option value="HR">HR</option>
                  <option value="Finance">Finance</option>
                  <option value="Marketing">Marketing</option>
                  <option value="Operations">Operations</option>
                </select>
              </div>
              
              <div class="form-group">
                <label>Gender</label>
                <select name="gender" id="gender" autocomplete="sex" required>
                  <option value="">Select Gender</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                  <option value="other">Other</option>
                </select>
              </div>
              
              <div class="form-group">
                <label>Date of Joining</label>
                <input type="date" name="date_of_joining" id="date_of_joining" autocomplete="off" required>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Status</label>
                <select name="status" id="status" autocomplete="off" required>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
              
              <div class="form-group">
                <label>Password</label>
                <div class="password-input-wrapper">
                  <input type="password" name="password" id="password" autocomplete="new-password">
                </div>
              </div>
              
              <div class="form-group">
                <label>Confirm Password</label>
                <div class="password-input-wrapper">
                  <input type="password" name="confirm_password" id="confirmPassword" autocomplete="new-password">
                  <span class="error-icon" id="passwordErrorIcon">⚠️</span>
                </div>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group full-width">
                <label>Address</label>
                <textarea name="address" id="address" autocomplete="street-address" required></textarea>
              </div>
            </div>
            
            <div class="form-row">
              <div class="form-group full-width">
                <input type="hidden" name="role" value="employee">
              </div>
            </div>

            <?php if (isset($upload_error)): ?>
              <div class="form-row">
                <div class="form-group full-width">
                  <div style="color: red; margin-top: 10px;"><?php echo htmlspecialchars($upload_error); ?></div>
                </div>
              </div>
            <?php endif; ?>

            <div class="form-actions">
              <button type="submit" class="add-employee-btn">Add Employee</button>
            </div>
          </div>

          <div class="profile-picture-section">
            <label>Profile Picture</label>
            <div class="profile-picture-placeholder" id="profilePicturePlaceholder">&#128100;</div>
            <input type="file" name="profile_picture" accept="image/*" id="profilePictureInput">
          </div>
        </form>
      </div>
    </div>
  </div>

  <div id="reasonPopup" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); z-index:2000; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:10px; padding:32px 28px; min-width:320px; max-width:95vw; box-shadow:0 8px 32px #0002; position:relative;">
      <button id="closeReasonPopupBtn" style="position:absolute; top:10px; right:18px; font-size:22px; color:#888; cursor:pointer; background:none; border:none; padding:5px; z-index:1001; width:fit-content; height:fit-content;">&times;</button>
      <h3 style="margin-top:0; color:#111;">Reason</h3>
      <div id="reasonPopupText" style="font-size:1.1em; color:#222; word-break:break-word;"></div>
    </div>
  </div>

  <script>
    function showAddEmployeeModal() {
      const modal = document.getElementById('addEmployeeModal');
      const form = modal.querySelector('form');
      
      // Reset form for adding
      form.reset();
      form.action = ''; // Submit to the same page for handling
      form.querySelector('input[name="action"]').value = 'add_employee';
      modal.querySelector('h2').textContent = 'Add New Employee';
      modal.querySelector('button[type="submit"]').textContent = 'Add Employee';
      
      // Show employee ID field for adding
      modal.querySelector('input[name="employee_id"]').parentNode.style.display = 'block'; // Ensure it's visible
      if (modal.querySelector('input[name="id"]')) { // Corrected selector to look for 'id'
          modal.querySelector('input[name="id"]').remove();
      }

      // Show password fields for adding
      modal.querySelector('#password').parentNode.parentNode.style.display = 'flex'; // Corrected to flex
      modal.querySelector('#confirmPassword').parentNode.parentNode.style.display = 'flex'; // Corrected to flex

      // Reset email validation feedback
      const emailInput = modal.querySelector('input[name="email"]');
      emailInput.style.border = '1px solid #ddd';
      emailInput.setCustomValidity('');
      const tooltip = emailInput.parentNode.querySelector('div:last-child'); // Assuming tooltip is the last child
      if (tooltip) tooltip.style.display = 'none';

      // Reset password validation feedback
      const passwordInput = modal.querySelector('#password');
      const confirmPasswordInput = modal.querySelector('#confirmPassword');
      const passwordErrorIcon = modal.querySelector('#passwordErrorIcon');
      passwordInput.value = ''; // Clear password fields
      confirmPasswordInput.value = '';
      passwordInput.style.border = '1px solid #ddd';
      confirmPasswordInput.style.border = '1px solid #ddd';
      passwordErrorIcon.classList.remove('show');
      passwordInput.setCustomValidity('');
      confirmPasswordInput.setCustomValidity('');

      // Reset profile picture preview
      const profilePicturePlaceholder = document.getElementById('profilePicturePlaceholder');
      profilePicturePlaceholder.innerHTML = '&#128100;'; // Restore icon
      profilePicturePlaceholder.style.border = 'none'; // Remove red border if any

      modal.style.display = 'flex';
    }
    
    function hideAddEmployeeModal() {
      document.getElementById('addEmployeeModal').style.display = 'none';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
      if (event.target == document.getElementById('addEmployeeModal')) {
        hideAddEmployeeModal();
      }
    }

    // Live profile picture preview
    const profilePictureInput = document.getElementById('profilePictureInput');
    const profilePicturePlaceholder = document.getElementById('profilePicturePlaceholder');

    profilePictureInput.addEventListener('change', function(event) {
      const file = event.target.files[0];
      const profilePicturePlaceholder = document.getElementById('profilePicturePlaceholder');
      
      if (file) {
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            alert('Please upload a valid image file (JPG, PNG, or GIF)');
            this.value = ''; // Clear the file input
            profilePicturePlaceholder.innerHTML = '&#128100;';
            profilePicturePlaceholder.style.border = '2px solid red';
            return;
        }
        
        // Validate file size (max 5MB)
        const maxSize = 5 * 1024 * 1024; // 5MB in bytes
        if (file.size > maxSize) {
            alert('File size should not exceed 5MB');
            this.value = ''; // Clear the file input
            profilePicturePlaceholder.innerHTML = '&#128100;';
            profilePicturePlaceholder.style.border = '2px solid red';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            profilePicturePlaceholder.innerHTML = '';
            profilePicturePlaceholder.appendChild(img);
            profilePicturePlaceholder.style.border = 'none';
        }
        reader.readAsDataURL(file);
      } else {
        profilePicturePlaceholder.innerHTML = '&#128100;';
        profilePicturePlaceholder.style.border = '2px solid red';
      }
    });

    // Add email validation
    const emailInput = document.querySelector('input[name="email"]');
    const form = document.querySelector('form[action=""]');

    function checkEmail(email) {
      return fetch('check_email.php?email=' + encodeURIComponent(email))
        .then(response => response.json())
        .then(data => data.exists);
    }

    // Create tooltip
    const tooltip = document.createElement('div');
    tooltip.style.position = 'absolute';
    tooltip.style.backgroundColor = '#333';
    tooltip.style.color = 'white';
    tooltip.style.padding = '5px 10px';
    tooltip.style.borderRadius = '4px';
    tooltip.style.fontSize = '12px';
    tooltip.style.display = 'none';
    tooltip.style.zIndex = '1000';
    tooltip.style.top = '100%';
    tooltip.style.right = '0';
    tooltip.style.marginTop = '5px';
    tooltip.style.whiteSpace = 'nowrap';
    tooltip.textContent = 'This email is already registered';

    // Create a wrapper for the input and tooltip
    const inputWrapper = document.createElement('div');
    inputWrapper.style.position = 'relative';
    inputWrapper.style.display = 'inline-block';
    inputWrapper.style.width = '100%';
    
    // Replace the input's parent with our wrapper
    emailInput.parentNode.insertBefore(inputWrapper, emailInput);
    inputWrapper.appendChild(emailInput);
    inputWrapper.appendChild(tooltip);

    // Remove padding from the input
    emailInput.style.paddingRight = '';

    // Show tooltip on hover over the input itself now
    emailInput.addEventListener('mouseenter', async function() {
        const email = this.value.trim();
        if (email) {
            try {
                const exists = await checkEmail(email);
                if (exists) {
                    tooltip.style.display = 'block';
                } else {
                    tooltip.style.display = 'none';
                }
            } catch (error) {
                console.error('Error checking email:', error);
                tooltip.style.display = 'none';
            }
        } else {
            tooltip.style.display = 'none';
        }
    });

    emailInput.addEventListener('mouseleave', function() {
      tooltip.style.display = 'none';
    });

    // Check email on input change
    emailInput.addEventListener('input', async function() {
      const email = this.value.trim();
      if (email) {
        try {
          const exists = await checkEmail(email);
          if (exists) {
            // errorIndicator.style.display = 'block'; // Removed
            emailInput.style.border = '1px solid #dc3545'; // Add red border
            emailInput.setCustomValidity('This email is already registered');
          } else {
            // errorIndicator.style.display = 'none'; // Removed
            emailInput.style.border = '1px solid #ddd'; // Reset border
            emailInput.setCustomValidity('');
            tooltip.style.display = 'none';
          }
        } catch (error) {
          console.error('Error checking email:', error);
          // errorIndicator.style.display = 'none'; // Removed
          emailInput.style.border = '1px solid #ddd'; // Reset border on error
          emailInput.setCustomValidity('');
          tooltip.style.display = 'none';
        }
      } else {
        // errorIndicator.style.display = 'none'; // Removed
        emailInput.style.border = '1px solid #ddd'; // Reset border
        emailInput.setCustomValidity('');
        tooltip.style.display = 'none';
      }
    });

    form.addEventListener('submit', async function(e) {
      const email = emailInput.value.trim();
      if (email) {
        try {
          const exists = await checkEmail(email);
          if (exists) {
            e.preventDefault();
            // errorIndicator.style.display = 'block'; // Removed
            emailInput.style.border = '1px solid #dc3545'; // Add red border
            emailInput.focus();
            // Show tooltip on submit if there's an error
            tooltip.style.display = 'block';
          } else {
              emailInput.style.border = '1px solid #ddd'; // Reset border
          }
        } catch (error) {
          console.error('Error checking email:', error);
          emailInput.style.border = '1px solid #ddd'; // Reset border on error
        }
      } else {
          emailInput.style.border = '1px solid #ddd'; // Reset border
      }
    });

    // Script to handle showing/hiding main sections based on hash (sidebar navigation)
    function showSection(sectionId) {
      // List all main section IDs within main-content
      const mainContentSections = ['dashboard', 'employee-list', 'request-list', 'leave-types'];
      const mainContentDiv = document.querySelector('.main-content');
      // const leaveTypesDiv = document.getElementById('leave-types'); // No longer needed here

      // Hide all sections initially
      mainContentSections.forEach(id => {
          const el = document.getElementById(id);
          if (el) el.style.display = 'none';
      });
      // if (leaveTypesDiv) leaveTypesDiv.style.display = 'none'; // No longer needed here

      // Show the requested section
      const targetSection = document.getElementById(sectionId);
      if (targetSection) {
          targetSection.style.display = 'block';
          // Ensure main content div is visible if any section inside it is shown
          if (mainContentDiv) mainContentDiv.style.display = 'block';
      } else {
          // If the section doesn't exist, show dashboard as fallback
          document.getElementById('dashboard').style.display = 'block';
          if (mainContentDiv) mainContentDiv.style.display = 'block';
      }

      // Update active class on sidebar links
      document.querySelectorAll('.sidebar a').forEach(link => {
        link.classList.remove('active');
      });
      const activeSidebarLink = document.querySelector(`.sidebar a[href="#${sectionId}"]`);
      if (activeSidebarLink) {
        activeSidebarLink.classList.add('active');
      }
      // If navigating to the request list, ensure a leave tab is active
      if (sectionId === 'request-list') {
        const activeTabButton = document.querySelector('#request-list .tab-btns .tab-btn.active');
        if (activeTabButton) {
          activeTabButton.click();
        } else {
          showTab('pending');
        }
      }
    }

    // Script to handle showing/hiding leave request tabs within the Request List section
    function showTab(tab) {
        // Hide all leave request tab content
        document.getElementById('pending-leaves').style.display = 'none';
        document.getElementById('history-leaves').style.display = 'none';

        // Show the requested leave request tab content
        document.getElementById(tab + '-leaves').style.display = '';

        // Update active class on tab buttons
      document.getElementById('pendingTab').classList.toggle('active', tab === 'pending');
      document.getElementById('historyTab').classList.toggle('active', tab === 'history');
    }

    // Show default main section on load or based on hash in URL
    window.addEventListener('load', () => {
        const section = window.location.hash.substring(1) || 'dashboard'; // Default to dashboard
        showSection(section);
    });

    // Handle hash changes in URL (e.g., when clicking sidebar links)
    window.addEventListener('hashchange', () => {
        const section = window.location.hash.substring(1) || 'dashboard';
        showSection(section);
    });

    // Password confirmation validation
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const passwordErrorIcon = document.getElementById('passwordErrorIcon');

    function validatePassword() {
      const password = passwordInput.value;
      const confirmPassword = confirmPasswordInput.value;
      
      // Only validate if confirm password has input
      if (confirmPassword) {
        if (password !== confirmPassword) {
          // Passwords do not match
          passwordErrorIcon.classList.add('show');
          confirmPasswordInput.setCustomValidity('Passwords do not match');
        } else {
          // Passwords match
          passwordErrorIcon.classList.remove('show');
          confirmPasswordInput.setCustomValidity('');
        }
      } else {
          // Confirm password is empty, no error yet
          passwordErrorIcon.classList.remove('show');
          confirmPasswordInput.setCustomValidity('');
      }
    }

    // Add event listeners for real-time validation
    passwordInput.addEventListener('input', validatePassword);
    confirmPasswordInput.addEventListener('input', validatePassword);

    // Also validate on form submission
    form.addEventListener('submit', function(e) {
      // Only validate passwords on submit if they are visible (i.e., in add mode)
      if (passwordInput.parentNode.parentNode.style.display !== 'none') {
          validatePassword();
          if (confirmPasswordInput.validity.customError) {
            e.preventDefault();
            confirmPasswordInput.focus();
          }
      }
      // Add debug logging for form submission
      console.log('Form submitted with data:', new FormData(form));
    });

    // Function to open the Add Employee Modal for editing
    function openEditEmployeeModal(editIconElement) {
        const employeeBox = editIconElement.closest('.employee-box');
        const modal = document.getElementById('addEmployeeModal');
        const form = modal.querySelector('form');

        // Populate form with employee data
        form.querySelector('input[name="employee_id"]').value = employeeBox.dataset.employee_id;
        form.querySelector('input[name="name"]').value = employeeBox.dataset.name;
        form.querySelector('input[name="email"]').value = employeeBox.dataset.email;
        form.querySelector('select[name="job_title"]').value = employeeBox.dataset.job_title;
        form.querySelector('input[name="contact_number"]').value = employeeBox.dataset.contact_number;
        form.querySelector('input[name="birthday"]').value = employeeBox.dataset.birthday;
        form.querySelector('select[name="department"]').value = employeeBox.dataset.department;
        form.querySelector('select[name="gender"]').value = employeeBox.dataset.gender;
        form.querySelector('input[name="date_of_joining"]').value = employeeBox.dataset.date_of_joining;
        form.querySelector('textarea[name="address"]').value = employeeBox.dataset.address;
        form.querySelector('select[name="role"]').value = employeeBox.dataset.role;
        
        // Set profile picture preview
        const profilePicturePlaceholder = document.getElementById('profilePicturePlaceholder');
        const profilePicturePath = employeeBox.dataset.profile_picture;
        if (profilePicturePath && profilePicturePath !== 'default-avatar.png') {
            profilePicturePlaceholder.innerHTML = '<img src="' + profilePicturePath + '" alt="Profile Picture">';
        } else {
            profilePicturePlaceholder.innerHTML = '&#128100;'; // Default icon
        }
        profilePicturePlaceholder.style.border = 'none'; // Remove red border if any

        // Change form action and button text for editing
        form.action = 'admin_dashboard.php'; // Ensure form submits to the correct page
        form.querySelector('input[name="action"]').value = 'edit_employee';
        modal.querySelector('h2').textContent = 'Edit Employee';
        modal.querySelector('button[type="submit"]').textContent = 'Save Changes';

        // Add hidden input for employee ID
        let idInput = form.querySelector('input[name="id"]');
        if (!idInput) {
            idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            form.appendChild(idInput);
        }
        idInput.value = employeeBox.dataset.id;

        // Hide employee ID field and password fields for editing
        modal.querySelector('input[name="employee_id"]').parentNode.style.display = 'none'; // Hide employee ID field
        modal.querySelector('#password').parentNode.parentNode.style.display = 'none'; // Hide password field row
        modal.querySelector('#confirmPassword').parentNode.parentNode.style.display = 'none'; // Hide confirm password field row

        // Reset email validation feedback
        const emailInput = modal.querySelector('input[name="email"]');
        emailInput.style.border = '1px solid #ddd';
        emailInput.setCustomValidity('');
        const tooltip = emailInput.parentNode.querySelector('div:last-child');
        if (tooltip) tooltip.style.display = 'none';

        // Reset password validation feedback (though fields are hidden)
        const passwordInput = modal.querySelector('#password');
        const confirmPasswordInput = modal.querySelector('#confirmPassword');
        const passwordErrorIcon = modal.querySelector('#passwordErrorIcon');
        passwordInput.value = ''; // Clear password fields
        confirmPasswordInput.value = '';
        passwordInput.style.border = '1px solid #ddd';
        confirmPasswordInput.style.border = '1px solid #ddd';
        passwordErrorIcon.classList.remove('show');
        passwordInput.setCustomValidity('');
        confirmPasswordInput.setCustomValidity('');

        modal.style.display = 'flex';
    }

    document.querySelector('form').addEventListener('submit', function(e) {
        const profilePictureInput = document.getElementById('profilePictureInput');
        const profilePicturePlaceholder = document.getElementById('profilePicturePlaceholder');
        
        // Check if we're in add mode (not edit mode)
        if (profilePictureInput.parentNode.parentNode.querySelector('h2').textContent === 'Add New Employee') {
            if (!profilePictureInput.files || profilePictureInput.files.length === 0) {
                e.preventDefault(); // Prevent form submission
                profilePicturePlaceholder.style.border = '2px solid red';
                alert('Please upload a profile picture');
                return false;
            }
        }
    });

    // Reason popup logic for admin dashboard
    function showReasonPopup(cell) {
      var text = cell.textContent;
      document.getElementById('reasonPopupText').textContent = text;
      document.getElementById('reasonPopup').style.display = 'flex';
    }
    document.getElementById('closeReasonPopupBtn').onclick = function() {
      document.getElementById('reasonPopup').style.display = 'none';
    };
    window.onclick = function(event) {
      if (event.target == document.getElementById('reasonPopup')) {
        document.getElementById('reasonPopup').style.display = 'none';
      }
      // Existing modal close logic for add employee
      if (event.target == document.getElementById('addEmployeeModal')) {
        hideAddEmployeeModal();
      }
    }

    // Sidebar navigation for Leave Types
    document.getElementById('leaveTypesSidebarBtn').onclick = function() {
      window.location.hash = 'leave-types';
    };

    // Add Leave Type Modal Logic
    function showAddLeaveTypeModal() {
        document.getElementById('addLeaveTypeModal').style.display = 'flex';
    }

    function hideAddLeaveTypeModal() {
        document.getElementById('addLeaveTypeModal').style.display = 'none';
    }

    // Attach event listener to the Add Leave Type button
    document.getElementById('openAddLeaveTypeModalBtn').addEventListener('click', showAddLeaveTypeModal);

    // Attach event listeners to close the Add Leave Type modal
    document.getElementById('closeAddLeaveTypeModalBtn').addEventListener('click', hideAddLeaveTypeModal);
    document.getElementById('cancelAddLeaveTypeModalBtn').addEventListener('click', hideAddLeaveTypeModal);

    // Close modal when clicking outside (update existing function)
    window.onclick = function(event) {
      if (event.target == document.getElementById('reasonPopup')) {
        document.getElementById('reasonPopup').style.display = 'none';
      }
      // Existing modal close logic for add employee
      if (event.target == document.getElementById('addEmployeeModal')) {
        hideAddEmployeeModal();
      }
      // Add modal close logic for add leave type
      if (event.target == document.getElementById('addLeaveTypeModal')) {
        hideAddLeaveTypeModal();
      }
    }

    // Add filter functionality
    function resetFilters() {
        document.getElementById('status_filter').value = '';
        document.getElementById('date_from').value = '';
        document.getElementById('date_to').value = '';
        document.getElementById('employee_filter').value = '';
        document.getElementById('leave_type_filter').value = '';
        document.getElementById('filterForm').submit();
    }

    // Export to CSV
    function exportToCSV() {
        const filters = new URLSearchParams(new FormData(document.getElementById('filterForm'))).toString();
        window.location.href = `export_leave_data.php?format=csv&${filters}`;
    }

    // Export to PDF
    function exportToPDF() {
        const filters = new URLSearchParams(new FormData(document.getElementById('filterForm'))).toString();
        window.location.href = `export_leave_data.php?format=pdf&${filters}`;
    }

    function exportData(format) {
        const statusFilter = document.getElementById('status_filter').value;
        const dateFrom = document.getElementById('date_from').value;
        const dateTo = document.getElementById('date_to').value;
        const employeeFilter = document.getElementById('employee_filter').value;
        const leaveTypeFilter = document.getElementById('leave_type_filter').value;

        // Build query string
        const params = new URLSearchParams({
            format: format,
            status_filter: statusFilter,
            date_from: dateFrom,
            date_to: dateTo,
            employee_filter: employeeFilter,
            leave_type_filter: leaveTypeFilter
        });

        // Redirect to export script
        window.location.href = `export_leave_data.php?${params.toString()}`;
    }

  </script>
</body>
</html> 