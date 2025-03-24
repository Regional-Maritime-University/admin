<?php
    session_start();

    if (! isset($_SESSION["adminLogSuccess"]) || $_SESSION["adminLogSuccess"] == false || ! isset($_SESSION["user"]) || empty($_SESSION["user"])) {
        header("Location: login.php");
    }

    $isUser = false;
    if (strtolower($_SESSION["role"]) == "developers") {
        $isUser = true;
    }

    if (isset($_GET['logout']) || ! $isUser) {
        session_destroy();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        header('Location: login.php');
    }

    $_SESSION["lastAccessed"] = time();

    require_once 'bootstrap.php';

    use Src\Controller\AdminController;

    require_once 'inc/admin-database-con.php';

    $admin = new AdminController($db, $user, $pass);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary-color: #003262;
            --secondary-color: #34495e;
            --accent-color: #3498db;
            --text-color: #ecf0f1;
            --danger-color: #e74c3c;
            --success-color: #2ecc71;
        }

        body {
            display: flex;
            min-height: 100vh;
            background-color: #f5f6fa;
            overflow: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background-color: var(--primary-color);
            color: var(--text-color);
            padding: 20px;
            transition: all 0.3s ease;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            top: 0;
            left: 0;
        }

        .sidebar.collapsed {
            width: 60px;
        }

        .sidebar.collapsed+.main-content {
            margin-left: 60px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 30px;
        }

        .logo h2 {
            font-size: 1.5rem;
            transition: opacity 0.3s;
        }

        .sidebar.collapsed .logo h2 {
            opacity: 0;
            width: 0;
        }

        .menu-groups {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .menu-group {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 15px;
        }

        .menu-group h3 {
            font-size: 0.8rem;
            text-transform: uppercase;
            margin-bottom: 10px;
            color: rgba(255, 255, 255, 0.6);
            transition: opacity 0.3s;
        }

        .sidebar.collapsed .menu-group h3 {
            opacity: 0;
        }

        .menu-items {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px;
            text-decoration: none;
            color: var(--text-color);
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .menu-item:hover {
            background-color: var(--secondary-color);
        }

        .menu-item i {
            width: 20px;
            text-align: center;
        }

        .menu-item span {
            transition: opacity 0.3s;
        }

        .sidebar.collapsed .menu-item span {
            opacity: 0;
            width: 0;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            padding: 20px;
            transition: all 0.3s ease;
            margin-left: 250px;
            height: 100vh;
            overflow-y: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .toggle-sidebar {
            background: none;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
            font-size: 1.5rem;
        }

        .search-bar {
            display: flex;
            gap: 10px;
        }

        .search-bar input {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 300px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-info h3 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .stat-info p {
            color: #666;
            font-size: 0.9rem;
        }

        .recent-activity,
        .upcoming-deadlines,
        .academic-actions {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .recent-activity h2,
        .upcoming-deadlines h2,
        .academic-actions h2 {
            margin-bottom: 20px;
            color: var(--primary-color);
        }

        .activity-list,
        .deadline-list,
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .activity-item,
        .deadline-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px;
            border-radius: 5px;
            background-color: #f8f9fa;
        }

        .activity-icon,
        .deadline-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .activity-details h4,
        .deadline-details h4 {
            margin-bottom: 5px;
        }

        .activity-details p,
        .deadline-details p {
            font-size: 0.9rem;
            color: #666;
        }

        .deadline-status {
            margin-left: auto;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .deadline-status.urgent {
            background-color: #ffebee;
            color: var(--danger-color);
        }

        .deadline-status.pending {
            background-color: #fff3e0;
            color: var(--warning-color);
        }

        .deadline-status.normal {
            background-color: #e8f5e9;
            color: var(--success-color);
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .action-btn:hover {
            background-color: var(--primary-color);
        }

        .action-btn i {
            font-size: 1.2rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {

            .search-bar input {
                width: 200px;
            }

            .sidebar {
                position: fixed;
                left: -250px;
                height: 100vh;
                z-index: 1000;
            }

            .sidebar.active {
                left: 0;
            }

            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .search-bar input {
                width: 150px;
            }
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
        }

        /* Modal Base Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        /* Modal Dialog - Container for modal content */
        .modal-dialog {
            position: relative;
            width: 100%;
            margin: 1.75rem auto;
            pointer-events: none;
        }

        /* Modal Content */
        .modal-content {
            position: relative;
            display: flex;
            flex-direction: column;
            width: 100%;
            pointer-events: auto;
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin: 0 20px;
        }

        /* Scrollable Modal */
        .modal-dialog.modal-scrollable {
            display: flex;
            max-height: calc(100% - 3.5rem);
            /* Account for margin */
        }

        .modal-dialog.modal-scrollable .modal-content {
            max-height: 100%;
            overflow: hidden;
        }

        .modal-dialog.modal-scrollable .modal-body {
            overflow-y: auto;
            /* Custom scrollbar styling */
            scrollbar-width: thin;
            scrollbar-color: var(--accent-color) #f1f1f1;
        }

        /* Custom scrollbar for webkit browsers */
        .modal-dialog.modal-scrollable .modal-body::-webkit-scrollbar {
            width: 6px;
        }

        .modal-dialog.modal-scrollable .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .modal-dialog.modal-scrollable .modal-body::-webkit-scrollbar-thumb {
            background: var(--accent-color);
            border-radius: 3px;
        }

        .modal-dialog.modal-scrollable .modal-body::-webkit-scrollbar-thumb:hover {
            background: var(--primary-color);
        }

        /* Modal Sizes */
        /* Default size */
        .modal-dialog {
            max-width: 500px;
        }

        /* Small modal */
        .modal-dialog.modal-sm {
            max-width: 300px;
        }

        /* Large modal */
        .modal-dialog.modal-lg {
            max-width: 800px;
        }

        /* Extra large modal */
        .modal-dialog.modal-xl {
            max-width: 1140px;
        }

        /* Fullscreen Modal Variations */
        .modal-dialog.modal-fullscreen {
            width: 100vw;
            max-width: none;
            height: 100vh;
            margin: 0;
        }

        .modal-dialog.modal-fullscreen .modal-content {
            height: 100%;
            border: 0;
            border-radius: 0;
        }

        /* Responsive Fullscreen Variations */
        @media (max-width: 576px) {
            .modal-dialog.modal-fullscreen-sm-down {
                width: 100vw;
                max-width: none;
                height: 100vh;
                margin: 0;
            }

            .modal-dialog.modal-fullscreen-sm-down .modal-content {
                height: 100%;
                border: 0;
                border-radius: 0;
            }
        }

        @media (max-width: 768px) {
            .modal-dialog.modal-fullscreen-md-down {
                width: 100vw;
                max-width: none;
                height: 100vh;
                margin: 0;
            }

            .modal-dialog.modal-fullscreen-md-down .modal-content {
                height: 100%;
                border: 0;
                border-radius: 0;
            }
        }

        @media (max-width: 992px) {
            .modal-dialog.modal-fullscreen-lg-down {
                width: 100vw;
                max-width: none;
                height: 100vh;
                margin: 0;
            }

            .modal-dialog.modal-fullscreen-lg-down .modal-content {
                height: 100%;
                border: 0;
                border-radius: 0;
            }
        }

        @media (max-width: 1200px) {
            .modal-dialog.modal-fullscreen-xl-down {
                width: 100vw;
                max-width: none;
                height: 100vh;
                margin: 0;
            }

            .modal-dialog.modal-fullscreen-xl-down .modal-content {
                height: 100%;
                border: 0;
                border-radius: 0;
            }
        }

        @media (max-width: 1400px) {
            .modal-dialog.modal-fullscreen-xxl-down {
                width: 100vw;
                max-width: none;
                height: 100vh;
                margin: 0;
            }

            .modal-dialog.modal-fullscreen-xxl-down .modal-content {
                height: 100%;
                border: 0;
                border-radius: 0;
            }
        }

        /* Modal Header */
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 1rem;
            border-bottom: 1px solid #dee2e6;
        }

        /* Modal Body */
        .modal-body {
            position: relative;
            flex: 1 1 auto;
            padding: 1rem 0;
        }

        /* Modal Footer */
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 1rem;
            border-top: 1px solid #dee2e6;
        }

        /* Close Button */
        .close-btn {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            line-height: 1;
            color: var(--danger-color);
            cursor: pointer;
            padding: 0.5rem;
        }

        .close-btn:hover {
            opacity: 0.75;
        }

        /* Modal Styles */

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--primary-color);
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .radio-group {
            display: flex;
            gap: 15px;
        }

        .radio-group label {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .cancel-btn,
        .submit-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .cancel-btn {
            background-color: #f8f9fa;
            color: var(--primary-color);
        }

        .submit-btn {
            background-color: var(--accent-color);
            color: white;
        }

        .cancel-btn:hover {
            background-color: #e9ecef;
        }

        .submit-btn:hover {
            background-color: var(--primary-color);
        }

        #customDateRange {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .btn-xs {
            padding: 1px 5px !important;
            font-size: 12px !important;
            line-height: 1.5 !important;
            border-radius: 3px !important;
        }

        input.transform-text,
        select.transform-text,
        textarea.transform-text {
            text-transform: uppercase !important;
        }

        /**
        Fee structure and Items
         */
        .fee-structure-form {
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
        }

        .fee-structure-items-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .no-items-message {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 5px;
            color: #666;
        }

        .no-items-message i {
            font-size: 1.2rem;
            color: var(--accent-color);
        }

        .fee-structure-item {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 40px;
            gap: 15px;
            align-items: start;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .fee-structure-item:hover {
            background-color: #f1f3f5;
        }

        .fee-structure-item select,
        .fee-structure-item input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .fee-structure-item input:focus,
        .fee-structure-item select:focus {
            border-color: var(--accent-color);
            outline: none;
        }

        .remove-item-btn {
            background: none;
            border: none;
            color: var(--danger-color);
            cursor: pointer;
            padding: 5px;
            font-size: 1.1rem;
            transition: transform 0.2s;
        }

        .remove-item-btn:hover {
            transform: scale(1.1);
        }

        .add-fee-structure-item-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 10px;
        }

        .add-fee-structure-item-btn:hover {
            background-color: var(--primary-color);
        }

        .amount-field {
            position: relative;
        }

        .amount-field::before {
            content: "GH₵";
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 14px;
        }

        .amount-field input {
            padding-left: 35px;
        }

        @media (max-width: 768px) {
            .fee-structure-item {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .remove-item-btn {
                justify-self: end;
            }
        }

        i.fas {
            cursor: pointer;
        }

        .custom-tooltip {
            --bs-tooltip-bg: var(--primary-color);
            --bs-tooltip-color: var(--text-color);
        }

        .pdf-file-container {
            position: relative;
            margin-bottom: 15px;
        }

        .pdf-file-preview {
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .pdf-file-preview {
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .existing-file-info {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        #pdf-filename {
            max-width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #change-pdf-btn {
            margin-left: 10px;
        }

        .pdf-icon {
            margin-right: 10px;
            color: #dc3545;
        }

        /* PDF File Display Styling */
        .pdf-file-info {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 5px;
        }

        .pdf-file-name {
            font-size: 14px;
            color: #333;
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pdf-view-icon {
            color: #e74c3c;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .pdf-view-icon:hover {
            transform: scale(1.1);
        }

        /* PDF Viewer Modal */
        .pdf-viewer-modal {
            display: none;
            position: fixed;
            z-index: 1100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.7);
        }

        .pdf-viewer-content {
            position: relative;
            background-color: #fefefe;
            margin: 2% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            height: 90%;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .pdf-viewer-close {
            position: absolute;
            top: 10px;
            right: 15px;
            color: #e74c3c;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            z-index: 1110;
        }

        .pdf-viewer-iframe {
            width: 100%;
            height: calc(100% - 20px);
            border: none;
        }
    </style>
    <link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">
    <script src="js/jquery-3.6.0.min.js"></script>
</head>

<body>

    <?php echo require_once "inc/navbar.php" ?>

    <main class="main-content">
        <div class="header">
            <button class="toggle-sidebar">
                <i class="fas fa-bars"></i>
            </button>
            <div class="search-bar">
                <input type="text" placeholder="Search...">
                <button class="toggle-sidebar">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background-color: var(--accent-color);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3>2,451</h3>
                    <p>Total Students</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background-color: var(--success-color);">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stat-info">
                    <h3>156</h3>
                    <p>New Applications</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background-color: var(--danger-color);">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-info">
                    <h3>85</h3>
                    <p>Active Courses</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background-color: var(--secondary-color);">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-info">
                    <h3>124</h3>
                    <p>Total Staff</p>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="recent-activity">
                <h2>Recent Activity</h2>
                <div class="activity-list">
                    <div class="activity-item">
                        <div class="activity-icon" style="background-color: var(--accent-color);">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="activity-details">
                            <h4>New Application Submitted</h4>
                            <p>John Doe applied for Computer Science program</p>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon" style="background-color: var(--success-color);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="activity-details">
                            <h4>Course Added</h4>
                            <p>Advanced Database Management added to IT Department</p>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon" style="background-color: var(--danger-color);">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="activity-details">
                            <h4>Admission Period Updated</h4>
                            <p>Fall 2025 admission period dates modified</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="upcoming-deadlines">
                <h2>Upcoming Deadlines</h2>
                <div class="deadline-list">
                    <div class="deadline-item">
                        <div class="deadline-icon" style="background-color: var(--accent-color);">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="deadline-details">
                            <h4>Fall Semester Registration</h4>
                            <p>Closes in 15 days</p>
                        </div>
                        <span class="deadline-status urgent">Urgent</span>
                    </div>
                    <div class="deadline-item">
                        <div class="deadline-icon" style="background-color: var(--success-color);">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="deadline-details">
                            <h4>Scholarship Applications</h4>
                            <p>Closes in 30 days</p>
                        </div>
                        <span class="deadline-status pending">Pending</span>
                    </div>
                    <div class="deadline-item">
                        <div class="deadline-icon" style="background-color: var(--warning-color);">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="deadline-details">
                            <h4>Internship Applications</h4>
                            <p>Closes in 45 days</p>
                        </div>
                        <span class="deadline-status normal">Normal</span>
                    </div>
                </div>
            </div>

            <div class="academic-actions">
                <h2>Academic Actions</h2>
                <div class="action-buttons">
                    <button class="action-btn" onclick="openAcademicYearModal()">
                        <i class="fas fa-clock"></i>
                        <span>Open/Close Academic Year</span>
                    </button>
                    <button class="action-btn" onclick="openAdmissionPeriodModal()">
                        <i class="fas fa-clock"></i>
                        <span>Open/Close Admission Period</span>
                    </button>
                    <button class="action-btn" onclick="openCourseRegistrationModal()">
                        <i class="fas fa-clock"></i>
                        <span>Open/Close Course Registration</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Academic Year Modal -->
        <div class="modal" id="academicYearModal">
            <div class="modal-dialog">
                <div class="modal-content">
                    <button class="close-btn" onclick="closeModal('academicYearModal')">×</button>
                    <h2>Manage Academic Year</h2>
                    <form id="academicYearForm">
                        <div class="form-group">
                            <label for="academicYear">Academic Year</label>
                            <select id="academicYear" required>
                                <option value="">Select Academic Year</option>
                                <option value="2024-2025">2024-2025</option>
                                <option value="2025-2026">2025-2026</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <div class="radio-group">
                                <label>
                                    <input type="radio" name="yearStatus" value="open" required>
                                    Open
                                </label>
                                <label>
                                    <input type="radio" name="yearStatus" value="close" required>
                                    Close
                                </label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="cancel-btn" onclick="closeModal('academicYearModal')">Cancel</button>
                            <button type="submit" class="submit-btn">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Admission Period Modal -->
        <div class="modal" id="admissionPeriodModal">
            <div class="modal-dialog">
                <div class="modal-content">
                    <button class="close-btn" onclick="closeModal('admissionPeriodModal')">×</button>
                    <h2>Create Admission Period</h2>
                    <form id="admissionPeriodForm">
                        <div class="form-group">
                            <label for="periodName">Period Name</label>
                            <input type="text" id="periodName" required placeholder="e.g., Fall 2025">
                        </div>
                        <div class="form-group">
                            <label for="startDate">Start Date</label>
                            <input type="date" id="startDate" required>
                        </div>
                        <div class="form-group">
                            <label for="endDate">End Date</label>
                            <input type="date" id="endDate" required>
                        </div>
                        <div class="form-group">
                            <label for="admissionType">Admission Type</label>
                            <select id="admissionType" required>
                                <option value="">Select Type</option>
                                <option value="undergraduate">Undergraduate</option>
                                <option value="graduate">Graduate</option>
                                <option value="international">International</option>
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="cancel-btn" onclick="closeModal('admissionPeriodModal')">Cancel</button>
                            <button type="submit" class="submit-btn">Create Period</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Admission Period Modal -->
        <div class="modal" id="courseRegistrationModal">
            <div class="modal-dialog">
                <div class="modal-content">
                    <button class="close-btn" onclick="closeModal('courseRegistrationModal')">×</button>
                    <h2>Create Admission Period</h2>
                    <form id="admissionPeriodForm">
                        <div class="form-group">
                            <label for="periodName">Period Name</label>
                            <input type="text" id="periodName" required placeholder="e.g., Fall 2025">
                        </div>
                        <div class="form-group">
                            <label for="startDate">Start Date</label>
                            <input type="date" id="startDate" required>
                        </div>
                        <div class="form-group">
                            <label for="endDate">End Date</label>
                            <input type="date" id="endDate" required>
                        </div>
                        <div class="form-group">
                            <label for="admissionType">Admission Type</label>
                            <select id="admissionType" required>
                                <option value="">Select Type</option>
                                <option value="undergraduate">Undergraduate</option>
                                <option value="graduate">Graduate</option>
                                <option value="international">International</option>
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="cancel-btn" onclick="closeModal('courseRegistrationModal')">Cancel</button>
                            <button type="submit" class="submit-btn">Create Period</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Reports Modal -->
        <div class="modal" id="reportsModal">
            <div class="modal-dialog">
                <div class="modal-content">
                    <button class="close-btn" onclick="closeModal('reportsModal')">×</button>
                    <h2>Generate Reports</h2>
                    <form id="reportsForm">
                        <div class="form-group">
                            <label for="reportType">Report Type</label>
                            <select id="reportType" required>
                                <option value="">Select Report Type</option>
                                <option value="enrollment">Enrollment Statistics</option>
                                <option value="application">Application Trends</option>
                                <option value="financial">Financial Summary</option>
                                <option value="graduation">Graduation Rates</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="reportPeriod">Period</label>
                            <select id="reportPeriod" required>
                                <option value="">Select Period</option>
                                <option value="current">Current Academic Year</option>
                                <option value="previous">Previous Academic Year</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                        <div class="form-group" id="customDateRange" style="display:none;">
                            <label>Custom Date Range</label>
                            <div>
                                <label>Start Date</label>
                                <input type="date" id="reportStartDate">
                                <label>End Date</label>
                                <input type="date" id="reportEndDate">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="cancel-btn" onclick="closeModal('reportsModal')">Cancel</button>
                            <button type="submit" class="submit-btn">Generate Report</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Existing sidebar toggle code...

        // Toggle sidebar
        const toggleButtons = document.querySelectorAll('.toggle-sidebar');
        const sidebar = document.querySelector('.sidebar');

        toggleButtons.forEach(button => {
            button.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                if (window.innerWidth <= 768) {
                    sidebar.classList.toggle('active');
                }
            });
        });

        // Responsive sidebar behavior
        window.addEventListener('resize', () => {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('collapsed');
                sidebar.classList.remove('active');
            }
        });

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Specific modal openers
        function openAcademicYearModal() {
            openModal('academicYearModal');
        }

        function openAdmissionPeriodModal() {
            openModal('admissionPeriodModal');
        }

        function openCourseRegistrationModal() {
            openModal('courseRegistrationModal');
        }

        function openReportModal() {
            openModal('reportsModal');
        }

        // Form submission handlers
        document.getElementById('academicYearForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const year = document.getElementById('academicYear').value;
            const status = document.querySelector('input[name="yearStatus"]:checked').value;
            showToast(`Academic Year ${year} ${status}d successfully`);
            closeModal('academicYearModal');
        });

        document.getElementById('admissionPeriodForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const periodName = document.getElementById('periodName').value;
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            showToast(`Admission Period ${periodName} created from ${startDate} to ${endDate}`);
            closeModal('admissionPeriodModal');
        });

        document.getElementById('courseRegistrationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const periodName = document.getElementById('periodName').value;
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            showToast(`Admission Period ${periodName} created from ${startDate} to ${endDate}`);
            closeModal('courseRegistrationModal');
        });

        document.getElementById('reportsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const reportType = document.getElementById('reportType').value;
            const reportPeriod = document.getElementById('reportPeriod').value;
            showToast(`${reportType.charAt(0).toUpperCase() + reportType.slice(1)} report for ${reportPeriod} period generated`);
            closeModal('reportsModal');
        });

        // Additional form interactions
        document.getElementById('reportPeriod').addEventListener('change', function() {
            const customDateRange = document.getElementById('customDateRange');
            customDateRange.style.display = this.value === 'custom' ? 'block' : 'none';
        });

        // Existing resize event listener for sidebar
        window.addEventListener('resize', () => {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('collapsed');
                sidebar.classList.remove('active');
            }
        });
    </script>
</body>

</html>