<?php
session_start();

if (! isset($_SESSION["lastAccessed"])) {
    $_SESSION["lastAccessed"] = time();
}

$_SESSION["currentAccess"] = time();
$diff                      = $_SESSION["currentAccess"] - $_SESSION["lastAccessed"];
if ($diff > 1800) {
    die(json_encode(["success" => false, "message" => "logout"]));
}

/*
* Designed and programmed by
* @Author: Francis A. Anlimah
*/

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET,POST,PUT,DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require "../bootstrap.php";

use Src\Controller\AdminController;
use Src\Controller\ExposeDataController;
use Src\Controller\UploadExcelDataController;
use Src\Core\Course;
use Src\Core\Department;
use Src\Core\FeeItem;
use Src\Core\FeeStructure;
use Src\Core\FeeStructureCategory;
use Src\Core\FeeStructureItem;
use Src\Core\FeeStructureType;
use Src\Core\Program;
use Src\Core\Staff;
use Src\Core\Student;

require_once '../inc/admin-database-con.php';

$expose                 = new ExposeDataController($db, $user, $pass);
$admin                  = new AdminController($db, $user, $pass);
$department             = new Department($db, $user, $pass);
$program                = new Program($db, $user, $pass);
$course                 = new Course($db, $user, $pass);
$student                = new Student($db, $user, $pass);
$fee_structure          = new FeeStructure($db, $user, $pass);
$fee_structure_item     = new FeeStructureItem($db, $user, $pass);
$fee_structure_category = new FeeStructureCategory($db, $user, $pass);
$fee_structure_type     = new FeeStructureType($db, $user, $pass);
$fee_item               = new FeeItem($db, $user, $pass);
$staff                  = new Staff($db, $user, $pass);

$data   = [];
$errors = [];

// All GET request will be sent here
if ($_SERVER['REQUEST_METHOD'] == "GET") {
    if ($_GET["url"] == "fetch-fee-structure-category") {
        die(json_encode(["success" => true, "data" => $fee_structure_category->fetch()]));
    } elseif ($_GET["url"] == "fetch-fee-structure-type") {
        die(json_encode(["success" => true, "data" => $fee_structure_type->fetch()]));
    } elseif ($_GET["url"] == "fetch-fee-item") {
        die(json_encode(["success" => true, "data" => $fee_item->fetch()]));
    }

    // All POST request will be sent here
} elseif ($_SERVER['REQUEST_METHOD'] == "POST") {

    if ($_GET["url"] == "admin-login") {

        if (! isset($_SESSION["_adminLogToken"]) || empty($_SESSION["_adminLogToken"])) {
            die(json_encode(["success" => false, "message" => "Invalid request: 1!"]));
        }

        if (! isset($_POST["_vALToken"]) || empty($_POST["_vALToken"])) {
            die(json_encode(["success" => false, "message" => "Invalid request: 2!"]));
        }

        if ($_POST["_vALToken"] !== $_SESSION["_adminLogToken"]) {
            die(json_encode(["success" => false, "message" => "Invalid request: 3!"]));
        }

        $username = $expose->validateText($_POST["username"]);
        $password = $expose->validatePassword($_POST["password"]);

        $result = $admin->verifyAdminLogin($username, $password);

        if (! $result) {
            $_SESSION['adminLogSuccess'] = false;
            die(json_encode(["success" => false, "message" => "Incorrect application username or password! "]));
        }

        $_SESSION['user']         = $result[0]["id"];
        $_SESSION['role']         = $result[0]["role"];
        $_SESSION['user_type']    = $result[0]["type"];
        $_SESSION["admin_period"] = $expose->getCurrentAdmissionPeriodID();

        if (strtoupper($result[0]['role']) == "VENDORS") {
            $_SESSION["vendor_id"] = $expose->getVendorPhoneByUserID($_SESSION["user"])[0]["id"];
        }

        $_SESSION['adminLogSuccess'] = true;
        die(json_encode(["success" => true, "message" => strtolower($result[0]["role"])]));
    }

    // backup database
    elseif ($_GET["url"] == "backup-data") {
        $dbs  = ["rmu_admissions"];
        $user = "root";
        $pass = "";
        $host = "localhost";

        if (! file_exists("../Backups")) {
            mkdir("../Backups");
        }

        foreach ($dbs as $db) {
            if (! file_exists("../Backups/$db")) {
                mkdir("../Backups/$db");
            }

            $file_name = $db . "_" . date("F_d_Y") . "@" . date("g_ia") . uniqid("_", false);
            $folder    = "../Backups/$db/$file_name" . ".sql";
            $d         = exec("mysqldump --user={$user} --password={$pass} --host={$host} {$db} --result-file={$folder}", $output);
            die(json_encode(["success" => true, "message" => $output]));
        }
    }

    // reset password
    elseif ($_GET["url"] == "reset-password") {
        if (! isset($_POST["currentPassword"]) || empty($_POST["currentPassword"])) {
            die(json_encode(["success" => false, "message" => "Current password field is required!"]));
        }

        if (! isset($_POST["newPassword"]) || empty($_POST["newPassword"])) {
            die(json_encode(["success" => false, "message" => "New password field is required!"]));
        }

        if (! isset($_POST["renewPassword"]) || empty($_POST["renewPassword"])) {
            die(json_encode(["success" => false, "message" => "Retype new password field is required!"]));
        }

        $currentPass = $expose->validatePassword($_POST["currentPassword"]);
        $newPass     = $expose->validatePassword($_POST["newPassword"]);
        $renewPass   = $expose->validatePassword($_POST["renewPassword"]);

        if ($newPass !== $renewPass) {
            die(json_encode(["success" => false, "message" => "New password entry mismatched!"]));
        }

        $userDetails = $admin->verifySysUserExistsByID($_SESSION["user"]);
        if (empty($userDetails)) {
            die(json_encode(["success" => false, "message" => "Failed to verify user account!"]));
        }

        $result = $admin->verifyAdminLogin($userDetails[0]["user_name"], $currentPass);
        if (! $result) {
            die(json_encode(["success" => false, "message" => "Incorrect current password!"]));
        }

        $changePassword = $admin->resetUserPassword($_SESSION["user"], $newPass);
        die(json_encode($changePassword));
    }

    //
    elseif ($_GET["url"] == "program-info") {
        if (! isset($_POST["prog"]) || empty($_POST["prog"])) {
            die(json_encode(["success" => false, "message" => "Missing input field"]));
        }
        $rslt = $admin->fetchAllFromProgramByName($_POST["prog"]);
        if (! $rslt) {
            die(json_encode(["success" => false, "message" => "Failed to fetch program's details for this applicant"]));
        }

        die(json_encode(["success" => true, "message" => $rslt]));
    }

    //Departments

    elseif ($_GET["url"] == "fetch-department") {
        if (! isset($_POST["department"]) || empty($_POST["department"])) {
            die(json_encode(["success" => false, "message" => "Department id is required!"]));
        }
        die(json_encode($program->fetch('id', $_POST["department"])));
    } elseif ($_GET["url"] == "add-department") {
        if (! isset($_POST["name"]) || empty($_POST["name"])) {
            die(json_encode(["success" => false, "message" => "Department name is required!"]));
        }
        if (! isset($_POST["hod"]) || empty($_POST["hod"])) {
            die(json_encode(["success" => false, "message" => "Department hod is required!"]));
        }
        die(json_encode($department->add($_POST)));
    } elseif ($_GET["url"] == "update-department") {
        if (! isset($_POST["department"]) || empty($_POST["department"])) {
            die(json_encode(["success" => false, "message" => "Department id is required!"]));
        }
        if (! isset($_POST["name"]) || empty($_POST["name"])) {
            die(json_encode(["success" => false, "message" => "Department name is required!"]));
        }
        if (! isset($_POST["hod"]) || empty($_POST["hod"])) {
            die(json_encode(["success" => false, "message" => "Department hod is required!"]));
        }
        die(json_encode($department->update($_POST)));
    } elseif ($_GET["url"] == "archive-department") {
        if (! isset($_POST["department"]) || empty($_POST["department"])) {
            die(json_encode(["success" => false, "message" => "Department id is required!"]));
        }
        die(json_encode($department->archive($_POST["department"])));
    } elseif ($_GET["url"] == "delete-department") {
        if (! isset($_POST["department"]) || empty($_POST["department"])) {
            die(json_encode(["success" => false, "message" => "Department id is required!"]));
        }
        die(json_encode($department->delete($_POST["department"])));
    } elseif ($_GET["url"] == "total-department") {
        die(json_encode($department->total($_POST["archived"])));
    }

    //programs

    elseif ($_GET["url"] == "fetch-staff") {
        if (! isset($_POST["staff"]) || empty($_POST["staff"])) {
            die(json_encode(["success" => false, "message" => "Staff id is required!"]));
        }
        die(json_encode($staff->fetch($_POST["key"], $_POST["value"], $_POST["archived"])));
    } elseif ($_GET["url"] == "add-staff") {
        if (! isset($_POST["name"]) || empty($_POST["name"])) {
            die(json_encode(["success" => false, "message" => "Staff name is required!"]));
        }
        if (! isset($_POST["hod"]) || empty($_POST["hod"])) {
            die(json_encode(["success" => false, "message" => "Staff hod is required!"]));
        }
        die(json_encode($staff->add($_POST)));
    } elseif ($_GET["url"] == "update-staff") {
        if (! isset($_POST["staff"]) || empty($_POST["staff"])) {
            die(json_encode(["success" => false, "message" => "Staff id is required!"]));
        }
        if (! isset($_POST["name"]) || empty($_POST["name"])) {
            die(json_encode(["success" => false, "message" => "Staff name is required!"]));
        }
        if (! isset($_POST["hod"]) || empty($_POST["hod"])) {
            die(json_encode(["success" => false, "message" => "Staff hod is required!"]));
        }
        die(json_encode($staff->update($_POST, $_POST["staff"])));
    } elseif ($_GET["url"] == "archive-staff") {
        if (! isset($_POST["staff"]) || empty($_POST["staff"])) {
            die(json_encode(["success" => false, "message" => "Staff id is required!"]));
        }
        die(json_encode($staff->archive($_POST["staff"])));
    } elseif ($_GET["url"] == "delete-staff") {
        if (! isset($_POST["staff"]) || empty($_POST["staff"])) {
            die(json_encode(["success" => false, "message" => "Staff id is required!"]));
        }
        die(json_encode($staff->delete($_POST["staff"])));
    } elseif ($_GET["url"] == "total-staff") {
        die(json_encode($staff->fetch($_POST["key"], $_POST["value"], $_POST["archived"])));
    }

    //programs

    elseif ($_GET["url"] == "fetch-program") {
        die(json_encode($program->fetch($_POST["key"], $_POST["value"], $_POST["archived"])));
    } elseif ($_GET["url"] == "add-program") {
        die(json_encode($program->add($_POST)));
    } elseif ($_GET["url"] == "update-program") {
        die(json_encode($program->update($_POST, $_POST["program"])));
    } elseif ($_GET["url"] == "archive-program") {
        die(json_encode($program->archive($_POST["program"])));
    } elseif ($_GET["url"] == "delete-program") {
        die(json_encode($program->delete($_POST["program"])));
    } elseif ($_GET["url"] == "total-program") {
        die(json_encode($program->fetch($_POST["key"], $_POST["value"], $_POST["archived"])));
    }

    //courses

    elseif ($_GET["url"] == "fetch-course") {
        if (! isset($_POST["code"]) || empty($_POST["code"])) {
            die(json_encode(["success" => false, "message" => "Course code is required!"]));
        }
        die(json_encode(["success" => true, "data" => $course->fetch("code", $_POST["code"])]));
    }
    //add
    elseif ($_GET["url"] == "add-course") {
        if (! isset($_POST["code"]) || empty($_POST["code"])) {
            die(json_encode(["success" => false, "message" => "Course code is required!"]));
        }
        if (! isset($_POST["name"]) || empty($_POST["name"])) {
            die(json_encode(["success" => false, "message" => "Course name is required!"]));
        }
        if (! isset($_POST["creditHours"]) || empty($_POST["creditHours"])) {
            die(json_encode(["success" => false, "message" => "Course credit hours is required!"]));
        }
        if (! isset($_POST["contactHours"]) || empty($_POST["contactHours"])) {
            die(json_encode(["success" => false, "message" => "Course contact hours is required!"]));
        }
        if (! isset($_POST["semester"]) || empty($_POST["semester"])) {
            die(json_encode(["success" => false, "message" => "Course semester is required!"]));
        }
        if (! isset($_POST["level"]) || empty($_POST["level"])) {
            die(json_encode(["success" => false, "message" => "Course level is required!"]));
        }
        if (! isset($_POST["category"]) || empty($_POST["category"])) {
            die(json_encode(["success" => false, "message" => "Course category is required!"]));
        }
        if (! isset($_POST["department"]) || empty($_POST["department"])) {
            die(json_encode(["success" => false, "message" => "Course department is required!"]));
        }
        die(json_encode($course->add($_POST)));
    }
    //edit
    elseif ($_GET["url"] == "edit-course") {
        if (! isset($_POST["code"]) || empty($_POST["code"])) {
            die(json_encode(["success" => false, "message" => "Course code is required!"]));
        }
        if (! isset($_POST["name"]) || empty($_POST["name"])) {
            die(json_encode(["success" => false, "message" => "Course name is required!"]));
        }
        if (! isset($_POST["creditHours"]) || empty($_POST["creditHours"])) {
            die(json_encode(["success" => false, "message" => "Course credit hours is required!"]));
        }
        if (! isset($_POST["contactHours"]) || empty($_POST["contactHours"])) {
            die(json_encode(["success" => false, "message" => "Course contact hours is required!"]));
        }
        if (! isset($_POST["semester"]) || empty($_POST["semester"])) {
            die(json_encode(["success" => false, "message" => "Course semester is required!"]));
        }
        if (! isset($_POST["level"]) || empty($_POST["level"])) {
            die(json_encode(["success" => false, "message" => "Course level is required!"]));
        }
        if (! isset($_POST["category"]) || empty($_POST["category"])) {
            die(json_encode(["success" => false, "message" => "Course category is required!"]));
        }
        if (! isset($_POST["department"]) || empty($_POST["department"])) {
            die(json_encode(["success" => false, "message" => "Course department is required!"]));
        }
        die(json_encode($course->update($_POST)));
    }
    //archive
    elseif ($_GET["url"] == "archive-course") {
        if (! isset($_POST["code"]) || empty($_POST["code"])) {
            die(json_encode(["success" => false, "message" => "Course code is required!"]));
        }
        die(json_encode($course->archive($_POST["code"])));
    }
    //delete
    elseif ($_GET["url"] == "delete-course") {
        if (! isset($_POST["code"]) || empty($_POST["code"])) {
            die(json_encode(["success" => false, "message" => "Course code is required!"]));
        }
        die(json_encode($course->archive($_POST["code"])));
    }
    //total
    elseif ($_GET["url"] == "total-course") {
        die(json_encode($program->fetch($_POST["key"], $_POST["value"], $_POST["archived"])));
    }
    //upload
    elseif ($_GET["url"] == "upload-course") {
        $result;
        if (! isset($_FILES["uploadCourseFile"]) || empty($_FILES["uploadCourseFile"])) {
            die(json_encode(["success" => false, "message" => "Invalid request!"]));
        }
        if ($_FILES["uploadCourseFile"]['error']) {
            die(json_encode(["success" => false, "message" => "Failed to upload file!"]));
        }

        $excelData = new UploadExcelDataController($_FILES["uploadCourseFile"], 4, 0);
        $result    = $excelData->run('course');
        die(json_encode($result));
    }

    // fee structure
    elseif ($_GET["url"] == "fetch-fee-structure") {
        if (isset($_POST["fee_structure"]) && ! empty($_POST["fee_structure"])) {
            $_POST["key"]   = "id";
            $_POST["value"] = $_POST["fee_structure"];
        } else if (isset($_POST["program"]) && ! empty($_POST["program"])) {
            $_POST["key"]   = "program";
            $_POST["value"] = $_POST["program"];
        } else if (isset($_POST["category"]) && ! empty($_POST["category"])) {
            $_POST["key"]   = "category";
            $_POST["value"] = $_POST["category"];
        } else if (isset($_POST["type"]) && ! empty($_POST["type"])) {
            $_POST["key"]   = "type";
            $_POST["value"] = $_POST["type"];
        } else if (isset($_POST["name"]) && ! empty($_POST["name"])) {
            $_POST["key"]   = "name";
            $_POST["value"] = $_POST["name"];
        } else {
            die(json_encode(["success" => false, "message" => "Missing a required input!"]));
        }
        die(json_encode(["success" => true, "data" => $fee_structure->fetch($_POST["key"], $_POST["value"])]));
    } elseif ($_GET["url"] == "add-fee-structure") {
        if (! isset($_POST["program"]) || empty($_POST["program"])) {
            die(json_encode(["success" => false, "message" => "Program is required!"]));
        }
        if (! isset($_POST["type"]) || empty($_POST["type"])) {
            die(json_encode(["success" => false, "message" => "Fee type is required!"]));
        }
        if (! isset($_POST["currency"]) || empty($_POST["currency"])) {
            die(json_encode(["success" => false, "message" => "Fee currency is required!"]));
        }
        if (! isset($_POST["category"]) || empty($_POST["category"])) {
            die(json_encode(["success" => false, "message" => "Fee category is required!"]));
        }
        if (! isset($_POST["member_amount"]) || empty($_POST["member_amount"])) {
            die(json_encode(["success" => false, "message" => "Member amount is required!"]));
        }
        if (! isset($_POST["non_member_amount"]) || empty($_POST["non_member_amount"])) {
            die(json_encode(["success" => false, "message" => "Non member amount is required!"]));
        }

        if (! isset($_FILES["fee_file"]) || empty($_FILES["fee_file"])) {
            die(json_encode($fee_structure->add($_POST)));
        } else {

            if ((isset($_FILES["fee_file"]) && ! empty($_FILES["fee_file"]))) {

                if ($_FILES["fee_file"]['error'] !== UPLOAD_ERR_OK) {
                    $error_message = "File upload error: ";
                    switch ($_FILES["fee_file"]['error']) {
                        case UPLOAD_ERR_INI_SIZE:
                            $error_message .= "The uploaded file exceeds the upload_max_filesize directive in php.ini";
                            break;
                        case UPLOAD_ERR_FORM_SIZE:
                            $error_message .= "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $error_message .= "The uploaded file was only partially uploaded";
                            break;
                        case UPLOAD_ERR_NO_FILE:
                            $error_message .= "No file was uploaded";
                            break;
                        case UPLOAD_ERR_NO_TMP_DIR:
                            $error_message .= "Missing a temporary folder";
                            break;
                        case UPLOAD_ERR_CANT_WRITE:
                            $error_message .= "Failed to write file to disk";
                            break;
                        case UPLOAD_ERR_EXTENSION:
                            $error_message .= "File upload stopped by extension";
                            break;
                        default:
                            $error_message .= "Unknown upload error";
                            break;
                    }
                    die(json_encode(["success" => false, "message" => $error_message]));
                }

                if (! in_array($_FILES["fee_file"]['type'], ['application/pdf', 'application/x-pdf'])) {
                    die(json_encode(["success" => false, "message" => "Only PDF files are allowed!"]));
                }

                // File size validation (limit to 10MB)
                $max_size = 10 * 1024 * 1024; // 10MB in bytes
                if ($_FILES["fee_file"]['size'] > $max_size) {
                    die(json_encode(["success" => false, "message" => "File size exceeds maximum limit of 10MB!"]));
                }
                die(json_encode($fee_structure->add($_POST, $_FILES["fee_file"])));
            } else {
                die(json_encode(["success" => false, "message" => "Fee file is required!"]));
            }
        }
    } elseif ($_GET["url"] == "update-fee-structure") {
        if (! isset($_POST["fee_structure"]) || empty($_POST["fee_structure"])) {
            die(json_encode(["success" => false, "message" => "Fee structure is required!"]));
        }
        if (! isset($_POST["program"]) || empty($_POST["program"])) {
            die(json_encode(["success" => false, "message" => "Program is required!"]));
        }
        if (! isset($_POST["type"]) || empty($_POST["type"])) {
            die(json_encode(["success" => false, "message" => "Fee type is required!"]));
        }
        if (! isset($_POST["currency"]) || empty($_POST["currency"])) {
            die(json_encode(["success" => false, "message" => "Fee currency is required!"]));
        }
        if (! isset($_POST["category"]) || empty($_POST["category"])) {
            die(json_encode(["success" => false, "message" => "Fee category is required!"]));
        }
        if (! isset($_POST["member_amount"]) || empty($_POST["member_amount"])) {
            die(json_encode(["success" => false, "message" => "Member amount is required!"]));
        }
        if (! isset($_POST["non_member_amount"]) || empty($_POST["non_member_amount"])) {
            die(json_encode(["success" => false, "message" => "Non member amount is required!"]));
        }
        if (! isset($_POST["file_existed"])) {
            die(json_encode(["success" => false, "message" => "Missing file parameter!"]));
        }
        if (! isset($_POST["new_file_uploaded"])) {
            die(json_encode(["success" => false, "message" => "Missing file upload parameter!"]));
        }

        $fileObj = null;
        if (!empty($_POST["new_file_uploaded"]) && isset($_FILES["fee_file"]) && ! empty($_FILES["fee_file"])) {
            if ($_FILES["fee_file"]['error'] !== UPLOAD_ERR_OK) {
                $error_message = "File upload error: ";
                switch ($_FILES["fee_file"]['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                        $error_message .= "The uploaded file exceeds the upload_max_filesize directive in php.ini";
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_message .= "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error_message .= "The uploaded file was only partially uploaded";
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $error_message .= "No file was uploaded";
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $error_message .= "Missing a temporary folder";
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $error_message .= "Failed to write file to disk";
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $error_message .= "File upload stopped by extension";
                        break;
                    default:
                        $error_message .= "Unknown upload error";
                        break;
                }
                die(json_encode(["success" => false, "message" => $error_message]));
            }

            if (! in_array($_FILES["fee_file"]['type'], ['application/pdf', 'application/x-pdf'])) {
                die(json_encode(["success" => false, "message" => "Only PDF files are allowed!"]));
            }

            // File size validation (limit to 10MB)
            $max_size = 10 * 1024 * 1024; // 10MB in bytes
            if ($_FILES["fee_file"]['size'] > $max_size) {
                die(json_encode(["success" => false, "message" => "File size exceeds maximum limit of 10MB!"]));
            }

            $fileObj = $_FILES["fee_file"];
        }

        die(json_encode($fee_structure->update($_POST, $fileObj)));
    } elseif ($_GET["url"] == "archive-fee-structure") {
        if (! isset($_POST["fee_structure"]) || empty($_POST["fee_structure"])) {
            die(json_encode(["success" => false, "message" => "Fee structure is required!"]));
        }
        die(json_encode($fee_structure->archive($_POST["fee_structure"])));
    } elseif ($_GET["url"] == "delete-fee-structure") {
        if (! isset($_POST["fee_structure"]) || empty($_POST["fee_structure"])) {
            die(json_encode(["success" => false, "message" => "Fee structure is required!"]));
        }
        die(json_encode($fee_structure->delete($_POST["fee_structure"])));
    } elseif ($_GET["url"] == "total-fee-structure") {
        die(json_encode($fee_structure->fetch($_POST["key"], $_POST["value"], $_POST["archived"])));
    }

    // Fee Items
    elseif ($_GET["url"] == "fetch-fee-structure-item") {
        if (isset($_POST["fee_item"]) && ! empty($_POST["fee_item"])) {
            $_POST["key"]   = "id";
            $_POST["value"] = $_POST["fee_item"];
        } else if (isset($_POST["fee_structure"]) && ! empty($_POST["fee_structure"])) {
            $_POST["key"]   = "fee";
            $_POST["value"] = $_POST["fee_structure"];
        } else if (isset($_POST["program"]) && ! empty($_POST["program"])) {
            $_POST["key"]   = "program";
            $_POST["value"] = $_POST["program"];
        } else if (isset($_POST["category"]) && ! empty($_POST["category"])) {
            $_POST["key"]   = "category";
            $_POST["value"] = $_POST["category"];
        } else if (isset($_POST["type"]) && ! empty($_POST["type"])) {
            $_POST["key"]   = "type";
            $_POST["value"] = $_POST["type"];
        } else if (isset($_POST["name"]) && ! empty($_POST["name"])) {
            $_POST["key"]   = "name";
            $_POST["value"] = $_POST["name"];
        } else {
            die(json_encode(["success" => false, "message" => "Missing a required input!"]));
        }
        die(json_encode(["success" => true, "data" => $fee_structure_item->fetch($_POST["key"], $_POST["value"])]));
    } elseif ($_GET["url"] == "add-fee-structure-item") {
        if (! isset($_POST["fee_structure"]) || empty($_POST["fee_structure"])) {
            die(json_encode(["success" => false, "message" => "Fee structure is required!"]));
        }
        if (! isset($_POST["items"]) || empty($_POST["items"])) {
            die(json_encode(["success" => false, "message" => "A fee item is required!"]));
        }
        die(json_encode($fee_structure_item->add($_POST)));
    }

    //students

    elseif ($_GET["url"] == "fetch-student") {
        die(json_encode($program->fetch($_POST["key"], $_POST["value"], $_POST["archived"])));
    } elseif ($_GET["url"] == "add-student") {
        die(json_encode($student->add($_POST)));
    } elseif ($_GET["url"] == "update-student") {
        die(json_encode($student->update($_POST)));
    } elseif ($_GET["url"] == "archive-student") {
        die(json_encode($student->archive($_POST)));
    } elseif ($_GET["url"] == "delete-student") {
        die(json_encode($student->delete($_POST)));
    } elseif ($_GET["url"] == "total-student") {
        die(json_encode($program->fetch($_POST["key"], $_POST["value"], $_POST["archived"])));
    }

    // All PUT request will be sent here
} else if ($_SERVER['REQUEST_METHOD'] == "PUT") {
    parse_str(file_get_contents("php://input"), $_PUT);

    // All DELETE request will be sent here
} else if ($_SERVER['REQUEST_METHOD'] == "DELETE") {
    parse_str(file_get_contents("php://input"), $_DELETE);
} else {
    http_response_code(405);
}
