<?php
session_start();

if (!isset($_SESSION["adminLogSuccess"]) || $_SESSION["adminLogSuccess"] == false || !isset($_SESSION["user"]) || empty($_SESSION["user"])) {
    header("Location: ../index.php");
}

$isUser = false;
if (strtolower($_SESSION["role"]) == "admin" || strtolower($_SESSION["role"]) == "developers") $isUser = true;

if (isset($_GET['logout']) || !$isUser) {
    session_destroy();
    $_SESSION = array();
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

if (!isset($_SESSION["_shortlistedFormToken"])) {
    $rstrong = true;
    $_SESSION["_shortlistedFormToken"] = hash('sha256', bin2hex(openssl_random_pseudo_bytes(64, $rstrong)));
    $_SESSION["vendor_type"] = "VENDOR";
}

$_SESSION["lastAccessed"] = time();

require_once('../bootstrap.php');

use Src\Controller\AdminController;
use Src\Core\Base;
use Src\Core\FeeStructure;

require_once('../inc/admin-database-con.php');

$admin = new AdminController($db, $user, $pass);
$fee_structure = new FeeStructure($db, $user, $pass);
$base = new Base($db, $user, $pass);

require_once('../inc/page-data.php');

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
            --primary-color: #2c3e50;
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
            gap: 5px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
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

        /* Modal Styles */
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

        .modal-content {
            background-color: white;
            border-radius: 10px;
            width: 100%;
            max-width: 500px;
            padding: 30px;
            position: relative;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .modal-content h2 {
            margin-bottom: 20px;
            color: var(--primary-color);
            text-align: center;
        }

        .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--danger-color);
        }

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

        .modal-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
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
    </style>
    <link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <link href="../assets/vendor/simple-datatables/style.css" rel="stylesheet">
    <script src="../js/jquery-3.6.0.min.js"></script>
</head>

<body>

    <?= require_once("../inc/navbar.php") ?>

    <main id="main" class="main-content">

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
                                    <th scope="col">Member Amount</th>
                                    <th scope="col">Non Member Amount</th>
                                    <th scope="col">Program</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $fee_structure_list = $fee_structure->fetch();
                                if (!empty($fee_structure_list) && is_array($fee_structure_list)) {
                                    $index = 1;
                                    foreach ($fee_structure_list as $aa) {
                                ?>
                                        <tr>
                                            <td><?= $index ?></td>
                                            <td><?= $aa["name"] ?></td>
                                            <td><?= $aa["type"] ?></td>
                                            <td><?= $aa["category"] ?></td>
                                            <td><?= $aa["member_amount"] ?></td>
                                            <td><?= $aa["non_member_amount"] ?></td>
                                            <td><a href="program/info.php?d=<?= $aa["program_id"] ?>"><?= $aa["program_name"] ?></a></td>
                                            <td>
                                                <a href="fee_structure/info.php?c=<?= $aa["id"] ?>" class="btn btn-primary btn-xs view-btn">View</a>
                                                <button id="<?= $aa["id"] ?>" class="btn btn-warning btn-xs edit-btn">Edit</button>
                                                <button id="<?= $aa["id"] ?>" class="btn btn-danger btn-xs archive-btn">Archive</button>
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
                                    <option value="<?= $program["id"] ?>"><?= $program["name"] ?></option>
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
                        <div class="input-group">
                            <div class="form-group col-5 me-2">
                                <label for="type">Type</label>
                                <select id="type" name="type" required>
                                    <option value="">Select</option>
                                    <option value="fresher">FRESHER</option>
                                    <option value="topup">TOPUP</option>
                                </select>
                            </div>
                            <div class="form-group col-6">
                                <label for="category">Category</label>
                                <select id="category" name="category" required>
                                    <option value="">Select</option>
                                    <option value="weekend">WEEKEND</option>
                                    <option value="regular">REGULAR</option>
                                </select>
                            </div>
                        </div>
                        <div class="input-group">
                            <div class="form-group me-2">
                                <label for="member_amount">Member Amount</label>
                                <input type="number" name="member_amount" min="0.00" id="member_amount" value="0.00" required>
                            </div>
                            <div class="form-group">
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

        <!-- Edit Staff Modal -->
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
                                    <option value="<?= $program["id"] ?>"><?= $program["name"] ?></option>
                                <?php
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit-currency">Currency</label>
                            <select id="edit-currency" name="edit-currency" required>
                                <option value="">Select</option>
                                <option value="USD">USD</option>
                                <option value="GHS">GHS</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <div class="form-group col-5 me-2">
                                <label for="edit-type">Type</label>
                                <select id="edit-type" name="type" required>
                                    <option value="">Select</option>
                                    <option value="fresher">FRESHER</option>
                                    <option value="topup">TOPUP</option>
                                </select>
                            </div>
                            <div class="form-group col-6">
                                <label for="edit-category">Category</label>
                                <select id="edit-category" name="category" required>
                                    <option value="">Select</option>
                                    <option value="weekend">WEEKEND</option>
                                    <option value="regular">REGULAR</option>
                                </select>
                            </div>
                        </div>
                        <div class="input-group">
                            <div class="form-group me-2">
                                <label for="edit-member_amount">Member Amount</label>
                                <input type="number" name="member_amount" min="0.00" id="edit-member_amount" value="0.00" required>
                            </div>
                            <div class="form-group">
                                <label for="edit-non_member_amount">Non Member Amount</label>
                                <input type="number" name="non_member_amount" min="0.00" id="edit-non_member_amount" value="0.00" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <input type="hidden" name="fee_structure" id="edit-fee_structure">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('editFeeStructureModal')">Cancel</button>
                            <button type="submit" class="btn btn-primary editFeeStructure-btn">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </main><!-- End #main -->

    <?= require_once("../inc/footer-section.php") ?>
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
        function openAddFeeStructureModal() {
            openModal('addFeeStructureModal');
        }

        function openEditFeeStructureModal() {
            openModal('editFeeStructureModal');
        }

        function openUploadFeeStructureModal() {
            openModal('uploadFeeStructureModal');
        }

        function setEditFormData(data) {
            $("#edit-fee_structure").val(data.id);
            $("#edit-program").val(data.program_id);
            $("#edit-currency").val(data.currency);
            $("#edit-type").val(data.type);
            $("#edit-category").val(data.category);
            $("#edit-member_amount").val(data.member_amount);
            $("#edit-non_member_amount").val(data.non_member_amount);
        }

        $(document).ready(function() {

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
                        console.log(result);
                        if (result.success) {
                            if (result.data) {
                                setEditFormData(result.data[0]);
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
                        console.log("error area: ", error);
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
                        console.log("error area: ", error);
                        alert("An error occurred while processing your request.");
                    }
                });
            });
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