<?php
session_start();

if (! isset($_SESSION["adminLogSuccess"]) || $_SESSION["adminLogSuccess"] == false || ! isset($_SESSION["user"]) || empty($_SESSION["user"])) {
    header("Location: ../index.php");
}

$isUser = false;
if (strtolower($_SESSION["role"]) == "admin" || strtolower($_SESSION["role"]) == "developers") {
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

    header('Location: ../' . $_SESSION["role"] . '/index.php');
}

if (! isset($_SESSION["_shortlistedFormToken"])) {
    $rstrong                           = true;
    $_SESSION["_shortlistedFormToken"] = hash('sha256', bin2hex(openssl_random_pseudo_bytes(64, $rstrong)));
    $_SESSION["vendor_type"]           = "VENDOR";
}

$_SESSION["lastAccessed"] = time();

require_once '../bootstrap.php';

use Src\Controller\AdminController;
use Src\Core\Base;
use Src\Core\FeeItem;
use Src\Core\FeeStructure;

require_once '../inc/admin-database-con.php';

$admin         = new AdminController($db, $user, $pass);
$fee_structure = new FeeStructure($db, $user, $pass);
$fee_item      = new FeeItem($db, $user, $pass);
$base          = new Base($db, $user, $pass);

require_once '../inc/page-data.php';

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
    <link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <link href="../assets/vendor/simple-datatables/style.css" rel="stylesheet">
    <script src="../js/jquery-3.6.0.min.js"></script>
</head>

<body>

    <?php echo require_once "../inc/navbar.php" ?>

    <main id="main" class="main-content">

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

        <div class="pagetitle">
            <h1>Fee Structure</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Fee Structure</li>
                </ol>
            </nav>
        </div><!-- End Page Title -->

        <section class="mb-4 section dashboard">
            <div style="display:flex; flex-direction: row-reverse;">
                <button class="action-btn btn btn-success btn-sm" onclick="openAddFeeStructureModal()">
                    <i class="fas fa-plus"></i>
                    <span>Add</span>
                </button>
            </div>
        </section>

        <section class="section dashboard">
            <div class="col-12">

                <div class="card recent-sales overflow-auto">

                    <div class="card-body">
                        <table class="table table-borderless datatable table-striped table-hover">
                            <thead class="table-secondary">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col" style="width:150px">Name</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Category</th>
                                    <th scope="col">Program</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $fee_structure_list = $fee_structure->fetch();
                                if (! empty($fee_structure_list) && is_array($fee_structure_list)) {
                                    $index = 1;
                                    foreach ($fee_structure_list as $fs) {
                                ?>
                                        <tr>
                                            <td><?php echo $index ?></td>
                                            <td><?php echo $fs["name"] ?></td>
                                            <td><?php echo $fs["type"] ?></td>
                                            <td><?php echo $fs["category"] ?></td>
                                            <td><a href="program/info.php?d=<?php echo $fs["program_id"] ?>"><?php echo $fs["program_name"] ?></a></td>
                                            <td>
                                                <i id="<?php echo $fs["id"] ?>" class="fas fa-eye text-primary view-btn me-2" title="View"></i>
                                                <i id="<?php echo $fs["id"] ?>" class="fas fa-edit text-warning edit-btn me-2" title="Edit"></i>
                                                <i id="<?php echo $fs["id"] ?>" class="fas fa-archive text-danger archive-btn" title="Archive"></i>
                                            </td>
                                        </tr>
                                <?php
                                        $index++;
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Add New Staff Modal -->
        <div class="modal" id="addFeeStructureItemsModal" tabindex="-1" aria-labelledby="addFeeStructureItemsModal" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-scrollable">
                <div class="modal-content">
                    <button class="close-btn" onclick="closeModal('addFeeStructureItemsModal')">×</button>
                    <h2>Add New Fee Structure</h2>
                    <form id="addFeeStructureItemsForm" method="POST" enctype="multipart/form-data">
                        <div class="fee-structure-form">
                            <div class="fee-structure-items-container">
                                <!-- No items message (shown when no items exist) -->
                                <div class="no-items-message" id="noItemsMessage">
                                    <i class="fas fa-info-circle"></i>
                                    <p>No fee items added yet. Click the button below to add items.</p>
                                </div>

                                <!-- Container for fee items (initially empty) -->
                                <div id="feeItemsList"></div>

                                <!-- Add Item Button -->
                                <button type="button" class="add-fee-structure-item-btn" onclick="addFeeStructureItem()">
                                    <i class="fas fa-plus"></i> Add Fee Item
                                </button>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <input type="hidden" name="fee_structure" id="add-item-fee_structure">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('addFeeStructureItemsModal')">Cancel</button>
                            <button type="submit" class="btn btn-primary itemsFeeStructure-btn">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Add New Fee Structure Modal -->
        <div class="modal" id="addFeeStructureModal">
            <div class="modal-dialog">
                <div class="modal-content">
                    <button class="close-btn" onclick="closeModal('addFeeStructureModal')">×</button>
                    <h2>Add New Fee Structure</h2>
                    <form id="addFeeStructureForm" method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="program">Program</label>
                            <select id="program" name="program" required>
                                <option value="" hidden>Select</option>
                                <?php
                                $programs = $admin->fetchAllPrograms();
                                foreach ($programs as $program) {
                                ?>
                                    <option value="<?php echo $program["id"] ?>"><?php echo $program["name"] ?></option>
                                <?php
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="currency">Currency</label>
                            <select id="currency" name="currency" required>
                                <option value="">Select</option>
                                <option value="USD" selected>USD</option>
                                <option value="GHS">GHS</option>
                            </select>
                        </div>
                        <div style="display: flex; justify-content:space-between;">
                            <div class="form-group" style="width: 49%">
                                <label for="type">Type</label>
                                <select id="type" name="type" required>
                                    <option value="">Select</option>
                                    <option value="fresher">FRESHER</option>
                                    <option value="topup">TOPUP</option>
                                </select>
                            </div>
                            <div class="form-group" style="width: 49%">
                                <label for="category">Category</label>
                                <select id="category" name="category" required>
                                    <option value="">Select</option>
                                    <option value="weekend">WEEKEND</option>
                                    <option value="regular">REGULAR</option>
                                    <?php
                                    $programs = $admin->fetchAllPrograms();
                                    foreach ($programs as $program) {
                                    ?>
                                        <option value="<?php echo $program["id"] ?>"><?php echo $program["name"] ?></option>
                                    <?php
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="fee_file">Fee File</label>
                            <input type="file" name="fee_file" id="fee_file">
                        </div>
                        <div style="display: flex; justify-content:space-between;">
                            <div class="form-group" style="width: 49%">
                                <label for="member_amount">Member Amount</label>
                                <input type="number" name="member_amount" min="0.00" id="member_amount" value="0.00" required>
                            </div>
                            <div class="form-group" style="width: 49%">
                                <label for="non_member_amount">Non Member Amount</label>
                                <input type="number" name="non_member_amount" min="0.00" id="non_member_amount" value="0.00" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('addFeeStructureModal')">Cancel</button>
                            <button type="submit" class="btn btn-primary addFeeStructure-btn">Add</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Fee Structure Modal -->
        <div class="modal" id="editFeeStructureModal">
            <div class="modal-dialog">
                <div class="modal-content">
                    <button class="close-btn" onclick="closeModal('editFeeStructureModal')">×</button>
                    <h2>Edit Fee Structure</h2>
                    <form id="editFeeStructureForm" method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="edit-program">Program</label>
                            <select id="edit-program" name="program" required>
                                <option value="" hidden>Select</option>
                                <?php
                                $programs = $admin->fetchAllPrograms();
                                foreach ($programs as $program) {
                                ?>
                                    <option value="<?php echo $program["id"] ?>"><?php echo $program["name"] ?></option>
                                <?php
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit-currency">Currency</label>
                            <select id="edit-currency" name="currency" required>
                                <option value="">Select</option>
                                <option value="USD">USD</option>
                                <option value="GHS">GHS</option>
                            </select>
                        </div>
                        <div style="display: flex; justify-content:space-between;">
                            <div class="form-group" style="width: 49%">
                                <label for="edit-type">Type</label>
                                <select id="edit-type" name="type" required>
                                    <option value="">Select</option>
                                    <option value="fresher">FRESHER</option>
                                    <option value="topup">TOPUP</option>
                                </select>
                            </div>
                            <div class="form-group" style="width: 49%">
                                <label for="edit-category">Category</label>
                                <select id="edit-category" name="category" required>
                                    <option value="">Select</option>
                                    <option value="weekend">WEEKEND</option>
                                    <option value="regular">REGULAR</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group pdf-file-container">
                            <label for="edit-fee_file">Fee File</label>
                            <div class="pdf-file-preview">
                                <div id="existing-pdf-info" class="existing-file-info">
                                    <div class="pdf-file-info">
                                        <span id="pdf-filename">No file uploaded</span>
                                        <i class="fas fa-file-pdf pdf-view-icon" id="view-pdf-btn" style="display: none;" onclick="openPdfViewer()">Open</i>
                                    </div>
                                    <button type="button" id="change-pdf-btn" class="btn btn-secondary btn-sm">Change File</button>
                                </div>
                                <input type="file" name="fee_file" id="edit-fee_file" accept=".pdf" style="display: none;" onchange="handlePdfFileChange(event)">
                            </div>
                        </div>
                        <div style="display: flex; justify-content:space-between;">
                            <div class="form-group" style="width: 49%">
                                <label for="edit-member_amount">Member Amount</label>
                                <input type="number" name="member_amount" min="0.00" id="edit-member_amount" required>
                            </div>
                            <div class="form-group" style="width: 49%">
                                <label for="edit-non_member_amount">Non Member Amount</label>
                                <input type="number" name="non_member_amount" min="0.00" id="edit-non_member_amount" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <input type="hidden" name="fee_structure" id="edit-fee_structure">
                            <input type="hidden" name="file_existed" id="edit-file_existed" value="0">
                            <input type="hidden" name="new_file_uploaded" id="edit-new_file_uploaded" value="0">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('editFeeStructureModal')">Cancel</button>
                            <button type="submit" class="btn btn-primary editFeeStructure-btn">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- PDF Viewer Modal -->
        <div id="pdfViewerModal" class="pdf-viewer-modal">
            <div class="pdf-viewer-content">
                <span class="pdf-viewer-close" onclick="closePdfViewer()">&times;</span>
                <iframe id="pdfViewerFrame" class="pdf-viewer-iframe" src=""></iframe>
            </div>
        </div>

    </main><!-- End #main -->

    <?php require_once "../inc/footer-section.php" ?>

    <script>
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            if (modalId == "addFeeStructureModal") {
                document.getElementById("addFeeStructureForm").reset();
            } else if (modalId == "editFeeStructureModal") {
                console.log(modalId)
                document.getElementById("editFeeStructureForm").reset();
            } else if (modalId == "uploadFeeStructureModal") {
                $("#upload-notification").text("");
                document.getElementById("uploadFeeStructureForm").reset();
            }
            document.getElementById(modalId).classList.remove('active');
        }

        // Specific modal openers
        function openaddFeeStructureItemsModal() {
            openModal('addFeeStructureItemsModal');
        }

        function openAddFeeStructureModal() {
            openModal('addFeeStructureModal');
        }

        function openEditFeeStructureModal() {
            openModal('editFeeStructureModal');
        }

        function openUploadFeeStructureModal() {
            openModal('uploadFeeStructureModal');
        }

        // function setEditFormData(data) {
        //     $("#edit-fee_structure").val(data.id);
        //     $("#edit-program").val(data.program_id);
        //     $("#edit-currency").val(data.currency);
        //     $("#edit-type").val(data.type);
        //     $("#edit-category").val(data.category);
        //     $("#edit-member_amount").val(data.member_amount);
        //     $("#edit-non_member_amount").val(data.non_member_amount);
        // }

        function setFeeStructureItemsFormData(data) {
            $("#add-item-fee_structure").val(data);
        }

        // All existing items of selected fee structure
        let feeStructureExistingItems = [];

        let ALL_FEE_ITEMS = [];

        // Track selected fee types
        let selectedFeeItems = new Set();

        // Counter for generating unique IDs
        let itemCounter = 0;

        // Function to get available fee types (excluding selected ones)
        function getAvailableFeeItems() {
            return ALL_FEE_ITEMS.filter(feeItem => !selectedFeeItems.has(feeItem.value));
        }

        // Function to create fee type options HTML
        function createFeeItemOptions(selectedValue = '') {
            const availableTypes = getAvailableFeeItems();
            let options = '<option value="">Select Fee Item</option>';

            // If a selectedValue is provided, always include it in the options
            // This ensures the current value shows in its own dropdown
            const selectedItem = ALL_FEE_ITEMS.find(item => item.value === selectedValue);
            if (selectedValue && selectedItem) {
                options += `<option value="${selectedItem.value}" selected>${selectedItem.label}</option>`;
            }

            // Add all other available types
            availableTypes.forEach(item => {
                if (item.value !== selectedValue) {
                    options += `<option value="${item.value}">${item.label}</option>`;
                }
            });

            return options;
        }

        // Function to handle fee type selection change
        function handleFeeItemChange(select) {
            const oldValue = select.dataset.previousValue;
            const newValue = select.value;

            // Remove old value from selected set if it exists
            if (oldValue) {
                selectedFeeItems.delete(oldValue);
            }

            // Add new value to selected set if it's not empty
            if (newValue) {
                selectedFeeItems.add(newValue);
            }

            // Store the new value as previous value
            select.dataset.previousValue = newValue;

            // Update all empty dropdowns
            updateEmptyDropdowns();
        }

        // Function to update all empty fee type dropdowns
        function updateEmptyDropdowns() {
            const allSelects = document.querySelectorAll('.fee-structure-item select[name="feeItem"]');
            allSelects.forEach(select => {
                if (!select.value) {
                    select.innerHTML = createFeeItemOptions();
                }
            });
        }

        // Function to add a new fee item
        function addFeeStructureItem(existingData = null) {
            const feeItemsList = document.getElementById('feeItemsList');
            const noItemsMessage = document.getElementById('noItemsMessage');

            // Hide the no items message
            noItemsMessage.style.display = 'none';

            // Create new fee item
            const itemId = `feeItem${itemCounter++}`;
            const feeItemDiv = document.createElement('div');
            feeItemDiv.className = 'fee-structure-item';
            feeItemDiv.id = itemId;

            // If we have existing data, use it to pre-populate the fields
            const feeItem = existingData ? existingData.name : '';
            const memberAmount = existingData ? existingData.member_amount : '';
            const nonMemberAmount = existingData ? existingData.non_member_amount : '';

            // If this is an existing item, add the ID as a data attribute
            if (existingData && existingData.id) {
                feeItemDiv.dataset.itemId = existingData.id;
            }

            feeItemDiv.innerHTML = `
                <select name="feeItem" required onchange="handleFeeItemChange(this)">
                    ${createFeeItemOptions(feeItem)}
                </select>

                <div class="amount-field">
                    <input type="number"
                        name="memberAmount"
                        placeholder="Member Amount"
                        step="0.01"
                        min="0"
                        required
                        value="${memberAmount}">
                </div>

                <div class="amount-field">
                    <input type="number"
                        name="nonMemberAmount"
                        placeholder="Non-Member Amount"
                        step="0.01"
                        min="0"
                        required
                        value="${nonMemberAmount}">
                </div>

                <button type="button"
                        class="remove-item-btn"
                        onclick="removeFeeStructureItem('${itemId}')">
                    <i class="fas fa-trash"></i>
                </button>
            `;

            feeItemsList.appendChild(feeItemDiv);

            // If we have a fee type, add it to selected types
            if (feeItem) {
                selectedFeeItems.add(feeItem);
                const select = feeItemDiv.querySelector('select[name="feeItem"]');
                select.dataset.previousValue = feeItem;
            }

            updateFeeStructureItemsFormState();
        }

        // Function to remove a fee item
        function removeFeeStructureItem(itemId) {
            const item = document.getElementById(itemId);
            const select = item.querySelector('select[name="feeItem"]');

            // Remove the fee type from selected set
            if (select.value) {
                selectedFeeItems.delete(select.value);
            }

            item.remove();
            updateFeeStructureItemsFormState();
            updateEmptyDropdowns();
        }

        // Function to update form state
        function updateFeeStructureItemsFormState() {
            const feeItemsList = document.getElementById('feeItemsList');
            const noItemsMessage = document.getElementById('noItemsMessage');

            // Show/hide no items message based on number of items
            if (feeItemsList.children.length === 0) {
                noItemsMessage.style.display = 'flex';
                // Clear selected fee types when no items exist
                selectedFeeItems.clear();
            } else {
                noItemsMessage.style.display = 'none';
            }
        }

        // Function to set existing fee items from database response
        function setExistingFeeStructureItems(data) {
            // Clear existing items first
            const feeItemsList = document.getElementById('feeItemsList');
            feeItemsList.innerHTML = '';
            selectedFeeItems.clear();

            // Reset counter to ensure clean IDs
            itemCounter = 0;

            data.forEach(item => {
                const formattedItem = {
                    id: item.id,
                    name: item.name,
                    member_amount: item.member_amount,
                    non_member_amount: item.non_member_amount
                };
                feeStructureExistingItems.push(formattedItem);
                addFeeStructureItem(formattedItem);
            });

            updateFeeStructureItemsFormState();
            updateEmptyDropdowns();
        }

        // Function to collect form data (call this when saving)
        function collectFormData() {
            const items = [];
            const feeItems = document.querySelectorAll('.fee-structure-item');

            feeItems.forEach(item => {
                const formItem = {
                    name: item.querySelector('select[name="feeItem"]').value,
                    memberAmount: parseFloat(item.querySelector('input[name="memberAmount"]').value),
                    nonMemberAmount: parseFloat(item.querySelector('input[name="nonMemberAmount"]').value)
                };

                // If this is an existing item, include the ID
                if (item.dataset.itemId) {
                    formItem.id = parseInt(item.dataset.itemId);
                }

                items.push(formItem);
            });

            return items;
        }

        // Initialize form state
        document.addEventListener('DOMContentLoaded', updateFeeStructureItemsFormState);


        // Example usage:
        async function loadFeeItems() {
            try {
                const response = await fetch(`../endpoint/fetch-fee-item`);
                const jsonData = await response.json();
                jsonData.data.forEach(item => {
                    const formattedItem = {
                        value: item.name,
                        label: item.name,
                    };
                    ALL_FEE_ITEMS.push(formattedItem);
                });
                console.log("loaded items", ALL_FEE_ITEMS);
            } catch (error) {
                console.error('Error loading fee structure:', error);
            }
        }

        async function loadFeeStructureCategories() {
            try {
                const response = await fetch(`../endpoint/fetch-fee-structure-category`);
                const data = await response.json();
                let options = '<option value="">Select</option>';
                data.data.forEach(item => {
                    options += `<option value="${item.name}">${item.name}</option>`;
                });
                document.querySelector("#category").innerHTML = options;
            } catch (error) {
                console.error('Error loading fee structure:', error);
            }
        }

        async function loadFeeStructureTypes() {
            try {
                const response = await fetch(`../endpoint/fetch-fee-structure-type`);
                const data = await response.json();
                let options = '<option value="">Select</option>';
                data.data.forEach(item => {
                    options += `<option value="${item.name}">${item.name}</option>`;
                });
                document.querySelector("#type").innerHTML = options;
            } catch (error) {
                console.error('Error loading fee structure:', error);
            }
        }

        async function submitFeeStructureData(formData) {
            const button = document.querySelector(".addFeeStructure-btn");

            try {
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Uploading...';

                const response = await fetch("../endpoint/add-fee-structure-item", {
                    method: "POST",
                    body: formData
                });

                const result = await response.json();
                console.log(result);

                if (result.success) {
                    alert(result.message);
                    closeModal("addFeeStructureModal");
                    location.reload();
                } else {
                    alert(result.message);
                }
            } catch (error) {
                alert('Error: Internal server error!');
            } finally {
                // Enable button and restore text
                button.disabled = false;
                button.innerHTML = 'Upload';
            }
        }

        // Function to handle PDF file change
        function handlePdfFileChange(event) {
            const fileInput = event.target;
            const pdfFilename = document.getElementById('pdf-filename');
            const file = fileInput.files[0];

            if (file) {
                // Check if it's a PDF
                if (file.type === 'application/pdf') {
                    pdfFilename.textContent = file.name;
                    pdfFilename.classList.remove('text-danger');
                    document.querySelector('#edit-new_file_uploaded').value = 1;
                } else {
                    // Reset if not a PDF
                    fileInput.value = ''; // Clear the file input
                    pdfFilename.textContent = 'Invalid file type. Please select a PDF.';
                    pdfFilename.classList.add('text-danger');
                    document.getElementById('edit-new_file_uploaded').value = 0;
                }
            } else {
                pdfFilename.textContent = 'No new PDF file uploaded';
            }
        }

        // Function to trigger file input when "Change File" is clicked
        document.getElementById('change-pdf-btn').addEventListener('click', function() {
            document.getElementById('edit-fee_file').click();
        });

        // Global variable to store current PDF path
        let currentPdfPath = '';

        // Function to handle editing a fee structure
        function editFeeStructure(feeStructureData) {
            // Populate form fields with the data
            document.getElementById('edit-program').value = feeStructureData.program_id;
            document.getElementById('edit-currency').value = feeStructureData.currency;
            document.getElementById('edit-type').value = feeStructureData.type;
            document.getElementById('edit-category').value = feeStructureData.category;
            document.getElementById('edit-member_amount').value = feeStructureData.member_amount;
            document.getElementById('edit-non_member_amount').value = feeStructureData.non_member_amount;
            document.getElementById('edit-fee_structure').value = feeStructureData.id;

            // Handle PDF file information
            const pdfFilename = document.getElementById('pdf-filename');
            const viewPdfBtn = document.getElementById('view-pdf-btn');

            if (feeStructureData.file) {
                pdfFilename.textContent = feeStructureData.file;
                currentPdfPath = `<?php echo BASE_URL ?>/uploads/fees/${feeStructureData.file}`;
                viewPdfBtn.style.display = 'inline-block';
                document.getElementById('edit-file_existed').value = feeStructureData.file;
            } else {
                pdfFilename.textContent = 'No file uploaded';
                viewPdfBtn.style.display = 'none';
                currentPdfPath = '';
                document.getElementById('edit-file_existed').value = '';
            }

            // Open the modal
            openModal('editFeeStructureModal');
        }

        // Function to handle PDF file change
        // function handlePdfFileChange(event) {
        //     const file = event.target.files[0];
        //     const pdfFilename = document.getElementById('pdf-filename');
        //     const viewPdfBtn = document.getElementById('view-pdf-btn');

        //     if (file) {
        //         pdfFilename.textContent = file.name;
        //         viewPdfBtn.style.display = 'none'; // Hide view button for new uploads until saved
        //     } else {
        //         if (currentPdfPath) {
        //             // Revert to current PDF if cancel selected
        //             pdfFilename.textContent = currentPdfPath.split('/').pop();
        //             viewPdfBtn.style.display = 'inline-block';
        //         } else {
        //             pdfFilename.textContent = 'No file uploaded';
        //             viewPdfBtn.style.display = 'none';
        //         }
        //     }
        // }

        // Open PDF viewer modal
        function openPdfViewer() {
            if (!currentPdfPath) return;

            const pdfViewerModal = document.getElementById('pdfViewerModal');
            const pdfViewerFrame = document.getElementById('pdfViewerFrame');

            // Set the iframe source to the PDF file
            pdfViewerFrame.src = currentPdfPath;

            // Show the modal
            pdfViewerModal.style.display = 'block';
        }

        // Close PDF viewer modal
        function closePdfViewer() {
            const pdfViewerModal = document.getElementById('pdfViewerModal');
            const pdfViewerFrame = document.getElementById('pdfViewerFrame');

            // Clear the iframe source
            pdfViewerFrame.src = '';

            // Hide the modal
            pdfViewerModal.style.display = 'none';
        }

        // Add event listener for the change PDF button
        document.addEventListener('DOMContentLoaded', function() {
            const changePdfBtn = document.getElementById('change-pdf-btn');
            const editFeeFile = document.getElementById('edit-fee_file');

            changePdfBtn.addEventListener('click', function() {
                editFeeFile.click();
            });
        });

        $(document).ready(function() {

            loadFeeStructureTypes();
            loadFeeStructureCategories();
            loadFeeItems();

            $("#addFeeStructureItemsForm").on("submit", function(e) {

                e.preventDefault();
                // Create a new FormData object
                var feeItems = collectFormData();
                var feeStructure = document.querySelector('#add-item-fee_structure').value;
                var formData = {
                    fee_structure: feeStructure,
                    items: feeItems,
                    existing_items: feeStructureExistingItems
                };

                // Set up ajax request
                $.ajax({
                    type: 'POST',
                    url: "../endpoint/add-fee-structure-item",
                    data: formData,
                    success: function(result) {
                        console.log(result);
                        if (result.success) {
                            alert(result.message);
                            closeModal("addFeeStructureModal");
                            location.reload();
                        } else alert(result.message);
                    },
                    error: function() {
                        alert('Error: Internal server error!');
                    },
                    ajaxStart: function() {
                        $(".addFeeStructure-btn").prop("disabled", true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Uploading...');
                    },
                    ajaxStop: function() {
                        $(".addFeeStructure-btn").prop("disabled", false).html('Upload');
                    }
                });
            });

            $("#addFeeStructureForm").on("submit", function(e) {

                e.preventDefault();

                // Create a new FormData object
                var formData = new FormData(this);

                // Set up ajax request
                $.ajax({
                    type: 'POST',
                    url: "../endpoint/add-fee-structure",
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(result) {
                        console.log(result);
                        if (result.success) {
                            alert(result.message);
                            closeModal("addFeeStructureModal");
                            location.reload();
                        } else alert(result.message);
                    },
                    error: function() {
                        alert('Error: Internal server error!');
                    },
                    ajaxStart: function() {
                        $(".addFeeStructure-btn").prop("disabled", true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Uploading...');
                    },
                    ajaxStop: function() {
                        $(".addFeeStructure-btn").prop("disabled", false).html('Upload');
                    }
                });
            });

            $("#editFeeStructureForm").on("submit", function(e) {

                e.preventDefault();

                // Create a new FormData object
                var formData = new FormData(this);

                // Set up ajax request
                $.ajax({
                    type: 'POST',
                    url: "../endpoint/update-fee-structure",
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(result) {
                        console.log(result);
                        if (result.success) {
                            alert(result.message);
                            closeModal("editFeeStructureModal");
                            location.reload();
                        } else alert(result.message);
                    },
                    error: function() {
                        alert('Error: Internal server error!');
                    },
                    ajaxStart: function() {
                        $(".editFeeStructure-btn").prop("disabled", true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Uploading...');
                    },
                    ajaxStop: function() {
                        $(".editFeeStructure-btn").prop("disabled", false).html('Upload');
                    }
                });
            });

            $(document).on("click", ".view-btn", function(e) {
                const fee_structure = $(this).attr('id');

                const formData = {
                    "fee_structure": fee_structure
                };

                setFeeStructureItemsFormData(fee_structure);

                $.ajax({
                    type: "POST",
                    url: "../endpoint/fetch-fee-structure-item",
                    data: formData,
                    success: function(result) {
                        if (result.success) {
                            if (result.data) {
                                setExistingFeeStructureItems(result.data);
                                updateFeeStructureItemsFormState();
                                openaddFeeStructureItemsModal();
                            } else {
                                updateFeeStructureItemsFormState();
                                openaddFeeStructureItemsModal();
                            }
                        } else {
                            if (result.message == "logout") {
                                alert('Your session expired. Please refresh the page to continue!');
                                window.location.href = "?logout=true";
                            } else {
                                alert(result.message);
                            }
                        }
                    },
                    error: function(error) {
                        console.log("error area: ", error);
                        alert("An error occurred while processing your request.");
                    }
                });
            });

            $(document).on("click", ".edit-btn", function(e) {
                const fee_structure = $(this).attr('id');
                const formData = {
                    "fee_structure": fee_structure
                };

                $.ajax({
                    type: "POST",
                    url: "../endpoint/fetch-fee-structure",
                    data: formData,
                    success: function(result) {
                        console.log("result", result);
                        if (result.success) {
                            if (result.data) {
                                editFeeStructure(result.data[0]);
                                openEditFeeStructureModal();
                            } else alert("No data found");
                        } else {
                            if (result.message == "logout") {
                                alert('Your session expired. Please refresh the page to continue!');
                                window.location.href = "?logout=true";
                            } else {
                                alert(result.message);
                            }
                        }
                    },
                    error: function(error) {
                        console.error("error area: ", error);
                        alert("An error occurred while processing your request.");
                    }
                });
            });

            $(document).on("click", ".archive-btn", function(e) {
                const fee_structure = $(this).attr('id');

                const confirmMessage = `Are you sure you want to delete this course?`;
                if (!confirm(confirmMessage)) return;

                const formData = {
                    "fee_structure": fee_structure
                };

                $.ajax({
                    type: "POST",
                    url: "../endpoint/archive-fee-structure",
                    data: formData,
                    success: function(result) {
                        console.log(result);
                        if (result.success) {
                            alert(result.message);
                            location.reload();
                        } else {
                            if (result.message == "logout") {
                                alert('Your session expired. Please refresh the page to continue!');
                                window.location.href = "?logout=true";
                            } else {
                                alert(result.message);
                            }
                        }
                    },
                    error: function(error) {
                        console.error("error area: ", error);
                        alert("An error occurred while processing your request.");
                    }
                });
            });

        });

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
    </script>
    <script src="https://cdn.jsdelivr.net/npm/gasparesganga-jquery-loading-overlay@2.1.7/dist/loadingoverlay.min.js"></script>
    <script>
        $(document).ready(function() {
            $(document).on({
                ajaxStart: function() {
                    // Show full page LoadingOverlay
                    $.LoadingOverlay("show");
                },
                ajaxStop: function() {
                    // Hide it after 3 seconds
                    $.LoadingOverlay("hide");
                }
            });
        });
    </script>
</body>

</html>