<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Leave Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .sidebar {
            background-color: #f8f9fa;
            border-right: 1px solid #dee2e6;
            height: 100vh;
            position: fixed;
            padding-top: 56px;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
            padding-top: 70px;
        }
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .nav-link {
            border-radius: 0.25rem;
        }
        .nav-link.active {
            background-color: #0d6efd;
            color: white !important;
        }
        .employee-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .employee-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .leave-box {
            transition: all 0.3s ease;
        }
        .leave-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Leave Management System</a>
            <div class="d-flex align-items-center">
                <span class="navbar-text me-3">Admin</span>
                <a href="logout.php" class="btn btn-outline-light">Logout</a>
            </div>
        </div>
    </nav>

    <div class="sidebar" style="width: 250px;">
        <div class="d-flex flex-column flex-shrink-0 p-3">
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="#dashboard" class="nav-link active" aria-current="page">
                        <i class="bi bi-speedometer2 me-2"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="#employee-list" class="nav-link">
                        <i class="bi bi-people me-2"></i> Employees
                    </a>
                </li>
                <li>
                    <a href="#request-list" class="nav-link">
                        <i class="bi bi-list-check me-2"></i> Leave Requests
                    </a>
                </li>
                <li>
                    <a href="#leave-types" class="nav-link">
                        <i class="bi bi-calendar-event me-2"></i> Leave Types
                    </a>
                </li>
                <li>
                    <a href="#reports" class="nav-link">
                        <i class="bi bi-bar-chart me-2"></i> Reports
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="main-content">