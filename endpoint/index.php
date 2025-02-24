<?php
session_start();

if (!isset($_SESSION["lastAccessed"])) $_SESSION["lastAccessed"] = time();
$_SESSION["currentAccess"] = time();
$diff = $_SESSION["currentAccess"] - $_SESSION["lastAccessed"];
if ($diff > 1800) die(json_encode(array("success" => false, "message" => "logout")));

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
use Src\Controller\DownloadExcelDataController;
use Src\Controller\DownloadAllExcelDataController;
use Src\Controller\UploadExcelDataController;
use Src\Controller\ExposeDataController;

use Src\Core\Department;
use Src\Core\Program;
use Src\Core\Course;
use Src\Core\FeeItem;
use Src\Core\FeeStructureItem;
use Src\Core\FeeStructure;
use Src\Core\FeeStructureCategory;
use Src\Core\FeeStructureType;
use Src\Core\Staff;
use Src\Core\Student;

require_once('../inc/admin-database-con.php');

$expose = new ExposeDataController($db, $user, $pass);
$admin = new AdminController($db, $user, $pass);
$department = new Department($db, $user, $pass);
$program = new Program($db, $user, $pass);
$course = new Course($db, $user, $pass);
$student = new Student($db, $user, $pass);
$fee_structure = new FeeStructure($db, $user, $pass);
$fee_structure_item = new FeeStructureItem($db, $user, $pass);
$fee_structure_category = new FeeStructureCategory($db, $user, $pass);
$fee_structure_type = new FeeStructureType($db, $user, $pass);
$fee_item = new FeeItem($db, $user, $pass);
$staff = new Staff($db, $user, $pass);

$data = [];
$errors = [];

// All GET request will be sent here
if ($_SERVER['REQUEST_METHOD'] == "GET") {
    if ($_GET["url"] == "programs") {
        if (isset($_GET["type"])) {
            $t = 0;
            if ($_GET["type"] != "All") {
                $t = (int) $_GET["type"];
            }
            $result = $admin->fetchPrograms($t);
            if (!empty($result)) {
                $data["success"] = true;
                $data["message"] = $result;
            } else {
                $data["success"] = false;
                $data["message"] = "No result found!";
            }
        }
        die(json_encode($data));
    } elseif ($_GET["url"] == "form-price") {
        if (!isset($_GET["form_key"]) || empty($_GET["form_key"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field")));
        }
        $rslt = $admin->fetchFormPrice($_GET["form_key"]);
        if (!$rslt) die(json_encode(array("success" => false, "message" => "Error fetching form price details!")));
        die(json_encode(array("success" => true, "message" => $rslt)));
    }
    //
    elseif ($_GET["url"] == "vendor-form") {
        if (!isset($_GET["vendor_key"]) || empty($_GET["vendor_key"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field")));
        }
        $rslt = $admin->fetchVendor($_GET["vendor_key"]);
        if (!$rslt) die(json_encode(array("success" => false, "message" => "Error fetching vendor details!")));
        die(json_encode(array("success" => true, "message" => $rslt)));
    }
    //
    elseif ($_GET["url"] == "prog-form") {
        if (!isset($_GET["prog_key"]) || empty($_GET["prog_key"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field")));
        }
        $rslt = $admin->fetchProgramme($_GET["prog_key"]);
        if (!$rslt) die(json_encode(array("success" => false, "message" => "Error fetching programme information!")));
        die(json_encode(array("success" => true, "message" => $rslt)));
    }
    //
    elseif ($_GET["url"] == "adp-form") {
        if (!isset($_GET["adp_key"]) || empty($_GET["adp_key"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field")));
        }
        $rslt = $admin->fetchAdmissionPeriodByID($_GET["adp_key"]);
        if (!$rslt) die(json_encode(array("success" => false, "message" => "Error fetching admissions information!")));
        die(json_encode(array("success" => true, "message" => $rslt)));
    }
    //
    elseif ($_GET["url"] == "user-form") {
        if (!isset($_GET["user_key"]) || empty($_GET["user_key"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field")));
        }
        $rslt = $admin->fetchSystemUser($_GET["user_key"]);
        if (!$rslt) die(json_encode(array("success" => false, "message" => "Error fetching user account information!")));
        die(json_encode(array("success" => true, "message" => $rslt)));
    }
    //
    elseif ($_GET["url"] == "programsByCategory") {
        if (!isset($_GET["cert-type"]) || empty($_GET["cert-type"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field")));
        }
        $rslt = $admin->fetchAllFromProgramByCode($_GET["cert-type"]);
        if (!$rslt) die(json_encode(array("success" => false, "message" => "Failed to fetch programs for this certificate category [{$_GET["cert-type"]}]!")));
        die(json_encode(array("success" => true, "message" => $rslt)));
    }

    //
    elseif ($_GET["url"] == "fetch-fee-structure-category") {
        die(json_encode(array("success" => true, "data" => $fee_structure_category->fetch())));
    } elseif ($_GET["url"] == "fetch-fee-structure-type") {
        die(json_encode(array("success" => true, "data" => $fee_structure_type->fetch())));
    } elseif ($_GET["url"] == "fetch-fee-item") {
        die(json_encode(array("success" => true, "data" => $fee_item->fetch())));
    }

    // All POST request will be sent here
} elseif ($_SERVER['REQUEST_METHOD'] == "POST") {

    if ($_GET["url"] == "admin-login") {

        if (!isset($_SESSION["_adminLogToken"]) || empty($_SESSION["_adminLogToken"]))
            die(json_encode(array("success" => false, "message" => "Invalid request: 1!")));
        if (!isset($_POST["_vALToken"]) || empty($_POST["_vALToken"]))
            die(json_encode(array("success" => false, "message" => "Invalid request: 2!")));
        if ($_POST["_vALToken"] !== $_SESSION["_adminLogToken"]) {
            die(json_encode(array("success" => false, "message" => "Invalid request: 3!")));
        }

        $username = $expose->validateText($_POST["username"]);
        $password = $expose->validatePassword($_POST["password"]);

        $result = $admin->verifyAdminLogin($username, $password);

        if (!$result) {
            $_SESSION['adminLogSuccess'] = false;
            die(json_encode(array("success" => false, "message" => "Incorrect application username or password! ")));
        }

        $_SESSION['user'] = $result[0]["id"];
        $_SESSION['role'] = $result[0]["role"];
        $_SESSION['user_type'] = $result[0]["type"];
        $_SESSION["admin_period"] = $expose->getCurrentAdmissionPeriodID();

        if (strtoupper($result[0]['role']) == "VENDORS") {
            $_SESSION["vendor_id"] = $expose->getVendorPhoneByUserID($_SESSION["user"])[0]["id"];
        }

        $_SESSION['adminLogSuccess'] = true;
        die(json_encode(array("success" => true,  "message" => strtolower($result[0]["role"]))));
    }

    // set admission period
    elseif ($_GET["url"] == "set-admission-period") {
        if (!isset($_POST["data"])) die(json_encode(array("success" => false, "message" => "Invalid request!")));
        if (empty($_POST["data"])) die(json_encode(array("success" => false, "message" => "Missing input in request!")));
        $_SESSION["admin_period"] = (int) $_POST["data"];
        die(json_encode(array("success" => true,  "message" => "Admisssion period changed!")));
    }

    // Resend verification code
    elseif ($_GET["url"] == "resend-code") {
        if (!isset($_POST["resend_code"])) die(json_encode(array("success" => false, "message" => "Invalid request!")));
        if (empty($_POST["resend_code"])) die(json_encode(array("success" => false, "message" => "Missing input!")));

        $code_type = $expose->validateText($_POST["resend_code"]);
        switch ($code_type) {
            case 'sms':
                // For vendor resend otp code
                if (isset($_SESSION["_verifySMSToken"]) && !empty($_SESSION["_verifySMSToken"]) && isset($_POST["_vSMSToken"]) && !empty($_POST["_vSMSToken"]) && $_POST["_vSMSToken"] == $_SESSION["_verifySMSToken"]) {

                    $vendorPhone = $expose->getVendorPhoneByUserID($_SESSION["user"]);

                    if (!empty($vendorPhone)) {
                        $response = $expose->sendOTP($vendorPhone[0]["phone_number"]);

                        if (isset($response["otp_code"])) {
                            $_SESSION['sms_code'] = $response["otp_code"];
                            $_SESSION['verifySMSCode'] = true;
                            $data["success"] = true;
                            $data["message"] = "Verification code sent!";
                        } else {
                            $data["success"] = false;
                            $data["message"] = $response["statusDescription"];
                        }
                    } else {
                        $data["success"] = false;
                        $data["message"] = "No phone number entry found for this user!";
                    }
                }

                // for user/applicant/online resend otp code
                else if (isset($_SESSION["_step5Token"]) && !empty($_SESSION["_step5Token"]) && isset($_POST["_v5Token"]) && !empty($_POST["_v5Token"]) && $_POST["_v5Token"] == $_SESSION["_step5Token"]) {

                    $to = $_SESSION["step4"]["country_code"] . $_SESSION["step4"]["phone_number"];
                    $response = $expose->sendOTP($to);

                    if (isset($response["otp_code"])) {
                        $_SESSION['sms_code'] = $response["otp_code"];
                        $data["success"] = true;
                        $data["message"] = "Verification code resent!";
                    } else {
                        $data["success"] = false;
                        $data["message"] = $response["statusDescription"];
                    }
                } else {
                    die(json_encode(array("success" => false, "message" => "Invalid OTP SMS request!")));
                }
                break;
        }
        die(json_encode($data));
    }

    // Get details on form
    elseif ($_GET["url"] == "formInfo") {
        if (!isset($_POST["form_id"]) || empty($_POST["form_id"])) {
            die(json_encode(array("success" => false, "message" => "Error: Form has not been set properly in database!")));
        }

        $form_id = $expose->validateInput($_POST["form_id"]);
        $result = $expose->getFormPriceA($form_id);

        if (empty($result)) die(json_encode(array("success" => false, "message" => "Forms' price has not set in the database!")));
        die(json_encode(array("success" => true, "message" => $result)));
    }

    //Vendor endpoint
    elseif ($_GET["url"] == "sellAction") {
        if (isset($_SESSION["_vendor1Token"]) && !empty($_SESSION["_vendor1Token"]) && isset($_POST["_v1Token"]) && !empty($_POST["_v1Token"]) && $_POST["_v1Token"] == $_SESSION["_vendor1Token"]) {

            if (!isset($_POST["first_name"]) || empty($_POST["first_name"])) {
                die(json_encode(array("success" => false, "message" => "Customer first name is required!")));
            }
            if (!isset($_POST["last_name"]) || empty($_POST["last_name"])) {
                die(json_encode(array("success" => false, "message" => "Customer last name is required!")));
            }
            if (!isset($_POST["formSold"]) || empty($_POST["formSold"])) {
                die(json_encode(array("success" => false, "message" => "Choose a type of form to sell!")));
            }
            if (!isset($_POST["country"]) || empty($_POST["country"])) {
                die(json_encode(array("success" => false, "message" => "Phone number's country code is required!")));
            }
            if (!isset($_POST["phone_number"]) || empty($_POST["phone_number"])) {
                die(json_encode(array("success" => false, "message" => "Customer's phone number is required!")));
            }

            $first_name = $expose->validateText($_POST["first_name"]);
            $last_name = $expose->validateText($_POST["last_name"]);
            $phone_number = $expose->validatePhone($_POST["phone_number"]);
            $country = $expose->validateCountryCode($_POST["country"]);
            $form_id = $expose->validateNumber($_POST["formSold"]);
            //$form_type = $expose->validateNumber($_POST["form_type"]);
            $form_price = $_POST["form_price"];

            $charPos = strpos($country, ")");
            $country_name = substr($country, ($charPos + 2));
            $country_code = substr($country, 1, ($charPos - 1));

            $_SESSION["vendorData"] = array(
                "first_name" => $first_name,
                "last_name" => $last_name,
                "country_name" => $country_name,
                "country_code" => $country_code,
                "phone_number" => $phone_number,
                "email_address" => "",
                "form_id" => $form_id,
                //"form_type" => $form_type,
                "pay_method" => "CASH",
                "amount" => $form_price,
                "vendor_id" => $_SESSION["vendor_id"],
                "admin_period" => $_SESSION["admin_period"]
            );

            if (!isset($_SESSION["vendorData"]) || empty($_SESSION["vendorData"]))
                die(json_encode(array("success" => false, "message" => "Failed in preparing data payload submitted!")));

            if (!$expose->vendorExist($_SESSION["vendorData"]["vendor_id"]))
                die(json_encode(array("success" => false, "message" => "Process can only be performed by a vendor!")));

            die(json_encode($admin->processVendorPay($_SESSION["vendorData"])));
        } else {
            die(json_encode(array("success" => false, "message" => "Invalid request!")));
        }
    }

    // International student ref number verification
    elseif ($_GET["url"] == "ref-number-verify") {

        if (!$expose->vendorExist($_SESSION["vendor_id"])) {
            die(json_encode(array("success" => false, "message" => "Process can only be performed by a vendor!")));
        }

        if (isset($_SESSION["_foreignFormToken"]) && !empty($_SESSION["_foreignFormToken"]) && isset($_POST["_FFToken"]) && !empty($_POST["_FFToken"]) && $_POST["_FFToken"] == $_SESSION["_foreignFormToken"]) {

            if (!isset($_POST["action"]) || empty($_POST["action"])) {
                die(json_encode(array("success" => false, "message" => "Action parameter is required!")));
            }

            if (!isset($_POST["ref-number"]) || empty($_POST["ref-number"])) {
                die(json_encode(array("success" => false, "message" => "Reference Number is required!")));
            }

            if (!isset($_POST["membership"]) || empty($_POST["membership"])) {
                die(json_encode(array("success" => false, "message" => "Membership is required!")));
            }

            switch ($_POST["action"]) {
                case 'approve':
                    $ref_number = $expose->validateText($_POST["ref-number"]);
                    $membership = $expose->validateText($_POST["membership"]);

                    $result = $admin->verifyInternationalApplicantRefNumber($ref_number);
                    if (empty($result)) die(json_encode(array("success" => false, "message" => "No match found for provided reference number!")));

                    // Ensure amount and rate are valid floats
                    $amount = floatval($result[0]['amount']);
                    $rate = floatval($result[0]['rate']);

                    $form_price = $amount * $rate;

                    if (isset($result[0]["app_number"]) && !empty($result[0]["app_number"])) {
                        $data = $admin->fetchForeignAppDetailsAppNumber($result[0]["app_number"]);
                        die(json_encode(array("success" => true, "exttrid" => $data[0]["id"], "message" => "Transaction succeeded!")));
                    } else {
                        $_SESSION["vendorData"] = array(
                            "first_name" => $result[0]["first_name"],
                            "last_name" => $result[0]["last_name"],
                            "country_name" => $result[0]["p_country_name"],
                            "country_code" => $result[0]["p_country_code"],
                            "phone_number" => $result[0]["phone_number"],
                            "email_address" => $result[0]["email_address"],
                            "form_id" => $result[0]["form"],
                            "pay_method" => "CASH",
                            "amount" => $form_price,
                            "vendor_id" => $_SESSION["vendor_id"],
                            "admin_period" => $_SESSION["admin_period"],
                            "ref_number" => $ref_number,
                            "is_international" => true,
                            "amount_paid" => 'USD ' . $amount
                        );

                        $res = $admin->processVendorPay($_SESSION["vendorData"]);

                        if (!empty($res) && isset($res['success']) && $res["success"] == true && isset($res["exttrid"]) && !empty($res["exttrid"])) {
                            $admin->updateForiegnPurchaseStatus($ref_number, 'approved', $res["app_number"]);
                            unset($res["app_number"]);
                        }

                        die(json_encode($res));
                    }
                    break;

                case 'decline':
                    $ref_number = $expose->validateText($_POST["ref-number"]);
                    $res = $admin->updateForiegnPurchaseStatus($ref_number, 'declined');
                    if ($res) die(json_encode(array("success" => true, "message" => "Request declined successfully!")));
                    break;

                default:
                    die(json_encode(array("success" => false, "message" => "Action unavailable!")));
                    break;
            }

            die(json_encode(array("success" => false, "message" => "Action unavailable!")));
        } else {
            die(json_encode(array("success" => false, "message" => "Invalid request!")));
        }
    }

    //
    elseif ($_GET["url"] == "apps-data") {
        if (!isset($_POST["action"]) || !isset($_POST["form_t"])) die(json_encode(array("success" => false, "message" => "Invalid input!")));
        if (empty($_POST["action"]) || empty($_POST["form_t"])) die(json_encode(array("success" => false, "message" => "Missing request!")));

        $v_action = $expose->validateText($_POST["action"]);
        $v_form_t = $expose->validateNumber($_POST["form_t"]);
        $data = array('action' => $v_action, 'country' => 'All', 'type' => $v_form_t, 'program' => 'All');
        $result = $admin->fetchAppsSummaryData($_SESSION["admin_period"], $data);
        if (empty($result)) die(json_encode(array("success" => false, "message" => "Empty result!")));
        die(json_encode(array("success" => true, "message" => $result)));
    }
    //
    elseif ($_GET["url"] == "applicants") {

        if (!isset($_POST["action"]) || !isset($_POST["country"]) || !isset($_POST["type"]) || !isset($_POST["program"])) {
            die(json_encode(array("success" => false, "message" => "Missing input!")));
        }
        if (empty($_POST["action"]) || empty($_POST["country"]) || empty($_POST["type"]) || empty($_POST["program"])) {
            die(json_encode(array("success" => false, "message" => "Missing input!")));
        }

        $result = $admin->fetchAppsSummaryData($_SESSION["admin_period"], $_POST);
        if (!empty($result)) {
            $data["success"] = true;
            $data["message"] = $result;
        } else {
            $data["success"] = false;
            $data["message"] = "No result found!";
        }
        die(json_encode($data));
    }

    //
    else if ($_GET["url"] == "checkPrintedDocument") {
        if (!isset($_POST["app"]) || empty($_POST["app"])) die(json_encode(array("success" => false, "message" => "Missing input!")));
        if (!empty($admin->updateApplicationStatus($_POST["app"], 'printed', 1))) die(json_encode(array("success" => true)));
        die(json_encode(array("success" => false, "message" => "Failed to updated printed status!")));
    }

    //
    elseif ($_GET["url"] == "getAllEnrolledApplicants") {

        if (!isset($_POST["cert-type"]) || !isset($_POST["prog-type"]))
            die(json_encode(array("success" => false, "message" => "Invalid input field")));
        if (empty($_POST["cert-type"]))
            die(json_encode(array("success" => false, "message" => "Missing input field")));

        //$result = $admin->getAllAdmittedApplicantsAllAll($_POST["cert-type"]);
        $result = $admin->fetchAllEnrolledApplicantsData($_POST["cert-type"], $_POST["prog-type"]);
        if (empty($result)) die(json_encode(array("success" => false, "message" => "No result found!")));
        die(json_encode(array("success" => true, "message" => $result)));
    }

    //
    elseif ($_GET["url"] == "getAllDeclinedApplicants") {

        if (!isset($_POST["cert-type"]))
            die(json_encode(array("success" => false, "message" => "Invalid input field")));
        if (empty($_POST["cert-type"]))
            die(json_encode(array("success" => false, "message" => "Missing input field")));

        $result = $admin->getAllDeclinedApplicantsAllAll($_POST["cert-type"]);
        if (empty($result)) die(json_encode(array("success" => false, "message" => "No result found!")));
        die(json_encode(array("success" => true, "message" => $result)));
    }

    //
    elseif ($_GET["url"] == "getUnadmittedApps") {

        if (!isset($_POST["cert-type"]) || !isset($_POST["prog-type"])) {
            die(json_encode(array("success" => false, "message" => "Invalid input field")));
        }
        if (empty($_POST["cert-type"]) || empty($_POST["prog-type"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field")));
        }

        $result = $admin->fetchAllUnadmittedApplicantsData($_POST["cert-type"], $_POST["prog-type"], $_SESSION["admin_period"]);

        if (empty($result)) die(json_encode(array("success" => false, "message" => "No result found!")));
        die(json_encode(array("success" => true, "message" => $result)));
    }

    //
    elseif ($_GET["url"] == "getUnadmittedShortApps") {
        $result = $admin->fetchAllUnadmittedShortApplicantsData($_SESSION["admin_period"]);

        if (empty($result)) die(json_encode(array("success" => false, "message" => "No result found!")));
        die(json_encode(array("success" => true, "message" => $result)));
    }

    //
    elseif ($_GET["url"] == "shortlist-qualified") {
        if (!isset($_POST["cert-type"]) || !isset($_POST["prog-type"])) {
            die(json_encode(array("success" => false, "message" => "Invalid input field")));
        }
        if (empty($_POST["cert-type"]) || empty($_POST["prog-type"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field")));
        }

        $result = $admin->shortlistQualifiedStudents($_POST["cert-type"], $_POST["prog-type"], $_SESSION["admin_period"]);
        if (empty($result)) {
            die(json_encode(array("success" => false, "message" => "No result found!")));
        }
        die(json_encode($result));
    }

    //
    elseif ($_GET["url"] == "admit-short") {
        $result = $admin->admitQualifiedShortApps($_SESSION["admin_period"]);
        if (empty($result)) {
            die(json_encode(array("success" => false, "message" => "No result found!")));
        }
        die(json_encode($result));
    }

    //
    elseif ($_GET["url"] == "downloadBS") {
        if (!isset($_POST["cert-type"]) || empty($_POST["cert-type"]))
            die(json_encode(array("success" => false, "message" => "Please choose a certificate type!")));
        $url = "https://office.rmuictonline.com/download-bs.php?a=bs&c=" . $_POST["cert-type"];
        die(json_encode(array("success" => true, "message" => $url)));
    }
    //
    elseif ($_GET["url"] == "getBroadsheetData") {

        if (!isset($_POST["cert-type"]) || empty($_POST["cert-type"]))
            die(json_encode(array("success" => false, "message" => "Please choose a certificate type!")));

        //$result = $admin->fetchAllAdmittedApplicantsData($_POST["cert-type"]);
        die(json_encode($admin->fetchAllSubmittedApplicantsData($_POST["cert-type"])));
    }
    //
    elseif ($_GET["url"] == "downloadAwaiting") {
        $url = "../download-awaiting-ds.php?a=as&c=awaiting&ap=" . $_SESSION['admin_period'];
        die(json_encode(array("success" => true, "message" => $url)));
    }
    //
    elseif ($_GET["url"] == "extra-awaiting-data") {

        if (!isset($_POST["action"]) || empty($_POST["action"])) {
            die(json_encode(array("success" => false, "message" => "Invalid request (1)!")));
        }

        $result;

        switch ($_POST["action"]) {
                // download broadsheet dbs
            case 'dbs':
                $broadsheet = new DownloadExcelDataController($_POST['c']);
                $file = $broadsheet->generateFile();
                $result = $broadsheet->downloadFile($file);
                break;

                // upload awaiting datasheet uad
            case 'uad':

                if (!isset($_FILES["awaiting-ds"]) || empty($_FILES["awaiting-ds"])) {
                    die(json_encode(array("success" => false, "message" => "Invalid request!")));
                }

                if ($_FILES["awaiting-ds"]['error']) {
                    die(json_encode(array("success" => false, "message" => "Failed to upload file!")));
                }

                $startRow = $expose->validateNumber($_POST['startRow']);
                $endRow = $expose->validateNumber($_POST['endRow']);

                $excelData = new UploadExcelDataController($_FILES["awaiting-ds"], $_POST['startRow'], $_POST['endRow']);
                $result = $excelData->run('awaiting');
                break;
        }

        die(json_encode($result));
    }

    ///
    elseif ($_GET["url"] == "form-price") {
        if (!isset($_POST["form_type"]) || !isset($_POST["form_price"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field")));
        }
        if (empty($_POST["form_type"]) || empty($_POST["form_price"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field")));
        }
        if (empty($_POST["form_name"]) || empty($_POST["form_name"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field")));
        }

        $result = [];

        switch ($_POST["action"]) {
            case 'add':
                $rslt = $admin->addFormPrice($_POST["form_type"], $_POST["form_name"], $_POST["form_price"]);
                if (!$rslt) {
                    die(json_encode(array("success" => false, "message" => "Failed to add price!")));
                }
                $result = array("success" => true, "message" => "Successfully added form price!");
                break;

            case 'update':
                $rslt = $admin->updateFormPrice($_POST["form_id"], $_POST["form_type"], $_POST["form_name"], $_POST["form_price"]);
                if (!$rslt) {
                    die(json_encode(array("success" => false, "message" => "Failed to update price!")));
                }
                $result = array("success" => true, "message" => "Successfully updated form price!");
                break;

            default:
                die(json_encode(array("success" => false, "message" => "Invalid action!")));
                break;
        }

        die(json_encode($result));
    }

    //
    elseif ($_GET["url"] == "vendor-sub-branches-group") {
        if (!isset($_POST["vendor_key"]) || empty($_POST["vendor_key"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field")));
        }
        $rslt = $admin->fetchVendorSubBranchesGrp($_POST["vendor_key"]);
        if (!$rslt) die(json_encode(array("success" => false, "message" => "Error fetching vendor details!")));
        die(json_encode(array("success" => true, "message" => $rslt)));
    }

    //
    elseif ($_GET["url"] == "vendor-sub-branches") {
        if (!isset($_POST["vendor_branch"]) || empty($_POST["vendor_branch"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field")));
        }
        $rslt = $admin->fetchVendorSubBranches($_POST["vendor_branch"]);
        if (!$rslt) die(json_encode(array("success" => false, "message" => "Error fetching vendor details!")));
        die(json_encode(array("success" => true, "message" => $rslt)));
    }
    //
    elseif ($_GET["url"] == "vendor-form") {

        if (!isset($_POST["v-action"]) || empty($_POST["v-action"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field: Ghana Card")));
        }
        if (!isset($_POST["v-name"]) || empty($_POST["v-name"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field: Vendor Name")));
        }
        if (!isset($_POST["v-code"]) || empty($_POST["v-code"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field: Company code")));
        }
        if (!isset($_POST["v-email"]) || empty($_POST["v-email"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field: Email Address")));
        }
        if (!isset($_POST["v-phone"]) || empty($_POST["v-phone"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field: Phone Number")));
        }
        if (!isset($_POST["v-api-user"]) || empty($_POST["v-api-user"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field: API User status")));
        }

        $user_data = array(
            "first_name" => $_POST["v-name"],
            "last_name" => "MAIN",
            "user_name" => $_POST["v-email"],
            "user_role" => "Vendors",
            "user_type" => "user",
            "vendor_company" => $_POST["v-name"],
            "company_code" => $_POST["v-code"],
            "vendor_role" => "Ops Head",
            "vendor_phone" => $_POST["v-phone"],
            "vendor_branch" => "MAIN",
            "api_user" => ($_POST["v-api-user"] == "YES" ? 1 : 0)
        );

        $privileges = array("select" => 1, "insert" => 1, "update" => 0, "delete" => 0);

        $result;
        switch ($_POST["v-action"]) {
            case 'add':
                $rslt = $admin->addSystemUser($user_data, $privileges);
                if (!$rslt["success"]) die(json_encode($rslt));
                $result = array("success" => true, "message" => "Successfully added vendor account!");
                break;

            case 'update':
                $rslt = $admin->updateVendor($_POST["v-id"], $_POST["v-email"], $_POST["v-phone"]);
                if (!$rslt["success"]) die(json_encode($rslt));
                $result = array("success" => true, "message" => "Successfully updated vendor account information!");
                break;
        }

        if (isset($_FILES["other-branches"]) && !empty($_FILES["other-branches"])) {
            if ($_FILES["other-branches"]['error']) {
                $result = array("success" => false, "message" => "Successfully {$_POST["v-action"]}ed vendor's account information");
            } else {
                $result = $admin->uploadCompanyBranchesData($_POST["v-name"], $_FILES["other-branches"]);
            }
        }

        die(json_encode($result));
    }
    //
    elseif ($_GET["url"] == "prog-form") {
        if (!isset($_POST["prog-name"]) || empty($_POST["prog-name"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field: Name")));
        }
        if (!isset($_POST["prog-type"]) || empty($_POST["prog-type"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field: Type")));
        }
        if (!isset($_POST["prog-wkd"]) || empty($_POST["prog-wkd"])) {
            $prog_wkd = "0";
        } else {
            $prog_wkd = "1";
        }
        if (!isset($_POST["prog-grp"]) || empty($_POST["prog-grp"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field: Group")));
        }

        $result;
        switch ($_POST["prog-action"]) {
            case 'add':
                $rslt = $admin->addProgramme($_POST["prog-name"], $_POST["prog-type"], $prog_wkd, $_POST["prog-grp"]);
                if (!$rslt) {
                    die(json_encode(array("success" => false, "message" => "Failed to add vendor!")));
                }
                $result = array("success" => true, "message" => "Successfully added vendor!");
                break;

            case 'update':
                $rslt = $admin->updateProgramme($_POST["prog-id"], $_POST["prog-name"], $_POST["prog-type"], $prog_wkd, $_POST["prog-grp"]);
                if (!$rslt) {
                    die(json_encode(array("success" => false, "message" => "Failed to update vendor information!")));
                }
                $result = array("success" => true, "message" => "Successfully updated vendor information!");
                break;
        }

        die(json_encode($result));
    }
    //
    elseif ($_GET["url"] == "adp-form-verify" && $_POST["adp-action"] == 'add') {
        if (!isset($_POST["adp-start"]) || empty($_POST["adp-start"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field: Start Date")));
        }
        if (!isset($_POST["adp-end"]) || empty($_POST["adp-end"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field: End Date")));
        }
        if (!isset($_POST["adp-desc"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field: Description")));
        }

        $desc = '';
        if (isset($_POST["adp-desc"]) && !empty($_POST["adp-desc"])) $desc = $_POST["adp-desc"];

        if ($admin->fetchCurrentAdmissionPeriod()) {
            die(json_encode(array(
                "success" => false,
                "message" => "An admission period is currently open! Do you want to still continue?"
            )));
        }
        die(json_encode(array("success" => true, "message" => "add")));
    }

    //
    elseif ($_GET["url"] == "adp-form") {
        if (!isset($_POST["adp-start"]) || empty($_POST["adp-start"]))
            die(json_encode(array("success" => false, "message" => "Missing input field: Start Date")));
        if (!isset($_POST["adp-end"]) || empty($_POST["adp-end"]))
            die(json_encode(array("success" => false, "message" => "Missing input field: End Date")));
        if (!isset($_POST["adp-intake"]) || empty($_POST["adp-intake"]))
            die(json_encode(array("success" => false, "message" => "Missing input field: Description")));
        if (!isset($_POST["adp-desc"]))
            die(json_encode(array("success" => false, "message" => "Missing input field: Description")));

        if (isset($_POST["adp-desc"]) && empty($_POST["adp-desc"])) $desc = '';

        $result;
        switch ($_POST["adp-action"]) {
            case 'add':
                $result = $admin->addAdmissionPeriod($_POST["adp-start"], $_POST["adp-end"], $_POST["adp-desc"], $_POST["adp-intake"]);
                break;
            case 'update':
                $result = $admin->updateAdmissionPeriod($_POST["adp-id"], $_POST["adp-start"], $_POST["adp-desc"]);
                break;
        }
        die(json_encode($result));
    }
    //
    elseif ($_GET["url"] == "user-form") {
        if (!isset($_POST["user-fname"]) || empty($_POST["user-fname"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field: First name")));
        }
        if (!isset($_POST["user-lname"]) || empty($_POST["user-lname"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field: Last name")));
        }
        if (!isset($_POST["user-email"]) || empty($_POST["user-email"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field: Email")));
        }
        if (!isset($_POST["user-role"]) || empty($_POST["user-role"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field: Role")));
        }
        if (!isset($_POST["user-type"]) || empty($_POST["user-type"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field: User Type")));
        }

        if ($_POST["user-role"] == "Vendors") {
            if (!isset($_POST["vendor-tin"]) || empty($_POST["vendor-tin"])) {
                die(json_encode(array("success" => false, "message" => "Missing input field: Ghana Card")));
            }
            if (!isset($_POST["vendor-phone"]) || empty($_POST["vendor-phone"])) {
                die(json_encode(array("success" => false, "message" => "Missing input field: Phone Number")));
            }
            if (!isset($_POST["vendor-company"]) || empty($_POST["vendor-company"])) {
                die(json_encode(array("success" => false, "message" => "Missing input field: Address")));
            }
            if (!isset($_POST["vendor-address"]) || empty($_POST["vendor-address"])) {
                die(json_encode(array("success" => false, "message" => "Missing input field: Address")));
            }
        }

        $user_data = array(
            "first_name" => $_POST["user-fname"],
            "last_name" => $_POST["user-lname"],
            "user_name" => $_POST["user-email"],
            "user_role" => $_POST["user-role"],
            "user_type" => $_POST["user-type"],
            "vendor_company" => $_POST["vendor-company"],
            "vendor_tin" => $_POST["vendor-tin"],
            "vendor_phone" => $_POST["vendor-phone"],
            "vendor_address" => $_POST["vendor-address"]
        );

        $privileges = array("select" => 1, "insert" => 0, "update" => 0, "delete" => 0);
        if (isset($_POST["privileges"]) && !empty($_POST["privileges"])) {
            foreach ($_POST["privileges"] as $privilege) {
                if ($privilege == "insert") $privileges["insert"] = 1;
                if ($privilege == "update") $privileges["update"] = 1;
                if ($privilege == "delete") $privileges["delete"] = 1;
            }
        }

        $result;
        switch ($_POST["user-action"]) {
            case 'add':
                $result = $admin->addSystemUser($user_data, $privileges);
                break;

            case 'update':
                $rslt = $admin->updateSystemUser($_POST, $privileges);
                if (!$rslt) {
                    die(json_encode(array("success" => false, "message" => "Failed to update admission information!")));
                }
                $result = array("success" => true, "message" => "Successfully updated admission information!");
                break;
        }

        die(json_encode($result));
    }

    // For sales report on accounts dashboard
    elseif ($_GET["url"] == "salesReport") {
        if (!isset($_POST["admission-period"])) die(json_encode(array("success" => false, "message" => "Invalid input request for admission period!")));
        if (!isset($_POST["from-date"])) die(json_encode(array("success" => false, "message" => "Invalid input request for from date!")));
        if (!isset($_POST["to-date"])) die(json_encode(array("success" => false, "message" => "Invalid input request for to date!")));
        if (!isset($_POST["form-type"])) die(json_encode(array("success" => false, "message" => "Invalid input request for form type!")));
        if (!isset($_POST["purchase-status"])) die(json_encode(array("success" => false, "message" => "Invalid input request for purchase status!")));
        if (!isset($_POST["payment-method"])) die(json_encode(array("success" => false, "message" => "Invalid input request for payment method!")));

        if ((!empty($_POST["from-date"]) && empty($_POST["to-date"])) || (!empty($_POST["to-date"]) && empty($_POST["from-date"])))
            die(json_encode(array("success" => false, "message" => "Date range (From - To) must be set!")));

        $result = $admin->fetchAllFormPurchases($_SESSION["admin_period"], $_POST);
        if (empty($result)) die(json_encode(array("success" => false, "message" => "No result found for given parameters!")));
        die(json_encode(array("success" => true, "message" => $result)));
    }

    // For sales report on vendor's dashboard
    elseif ($_GET["url"] == "vendorSalesReport") {
        if (!isset($_POST["admission-period"])) die(json_encode(array("success" => false, "message" => "Invalid input request for admission period!")));
        if (!isset($_POST["from-date"])) die(json_encode(array("success" => false, "message" => "Invalid input request for from date!")));
        if (!isset($_POST["to-date"])) die(json_encode(array("success" => false, "message" => "Invalid input request for to date!")));
        if (!isset($_POST["form-type"])) die(json_encode(array("success" => false, "message" => "Invalid input request for form type!")));
        if (!isset($_POST["purchase-status"])) die(json_encode(array("success" => false, "message" => "Invalid input request for purchase status!")));

        if ((!empty($_POST["from-date"]) && empty($_POST["to-date"])) || (!empty($_POST["to-date"]) && empty($_POST["from-date"])))
            die(json_encode(array("success" => false, "message" => "Date range (From - To) must be set!")));

        $_POST["vendor-id"] = $_SESSION["vendor_id"];

        $result = $admin->fetchAllVendorFormPurchases($_SESSION["admin_period"], $_POST);
        if (empty($result)) die(json_encode(array("success" => false, "message" => "No result found for given parameters!")));
        die(json_encode(array("success" => true, "message" => $result)));
    }

    //
    elseif ($_GET["url"] == "purchaseInfo") {
        if (!isset($_POST["_data"]) || empty($_POST["_data"]))
            die(json_encode(array("success" => false, "message" => "Invalid request!")));
        $transID = $expose->validateNumber($_POST["_data"]);
        $result = $admin->fetchFormPurchaseDetailsByTranID($transID);
        if (empty($result)) die(json_encode(array("success" => false, "message" => "No result found!")));
        die(json_encode(array("success" => true, "message" => $result)));
    }

    // send purchase info
    elseif ($_GET["url"] == "gen-send-purchase-info") {
        if (!isset($_POST["genSendTransID"]) || empty($_POST["genSendTransID"]))
            die(json_encode(array("success" => false, "message" => "Invalid request!")));
        $transID = $expose->validateNumber($_POST["genSendTransID"]);
        die(json_encode($admin->sendPurchaseInfo($transID)));
    }

    // send purchase info
    elseif ($_GET["url"] == "send-purchase-info") {
        if (!isset($_POST["sendTransID"]) || empty($_POST["sendTransID"]))
            die(json_encode(array("success" => false, "message" => "Invalid request!")));
        $transID = $expose->validateNumber($_POST["sendTransID"]);
        die(json_encode($admin->sendPurchaseInfo($transID, false)));
    }

    // send purchase info
    elseif ($_GET["url"] == "verify-transaction-status") {
        if (!isset($_POST["verifyTransID"]) || empty($_POST["verifyTransID"]))
            die(json_encode(array("success" => false, "message" => "Invalid request:  transaction!")));
        if (!isset($_POST["payMethod"]) || empty($_POST["payMethod"]))
            die(json_encode(array("success" => false, "message" => "Invalid request: payment method!")));
        $transID = $expose->validateNumber($_POST["verifyTransID"]);
        die(json_encode($admin->verifyTransactionStatus($_POST["payMethod"], $transID, false)));
    }

    // send an sms to customer
    elseif ($_GET["url"] == "sms-customer") {
        if (!isset($_POST["recipient"]) || empty($_POST["recipient"]))
            die(json_encode(array("success" => false, "message" => "No recipient!")));
        if (!isset($_POST["message"]) || empty($_POST["message"]))
            die(json_encode(array("success" => false, "message" => "No message typed!")));
        if (strlen($_POST["message"]) > 160)
            die(json_encode(array("success" => false, "message" => "Message is too long. Maximum allowed is 160 characters!")));

        // Send SMS message
        $to = str_replace(array("+", "(", ")", " "), "", $_POST["recipient"]);
        $response = json_decode($expose->sendSMS($to, $_POST["message"]));
        if (!$response->status) die(json_encode(array("success" => true, "message" => "Message sent successfully!")));
        die(json_encode(array("success" => true, "message" => "Failed to send message!")));
    }

    // fetch group sales data
    elseif ($_GET["url"] == "group-sales-report") {
        if (!isset($_POST["from-date"])) die(json_encode(array("success" => false, "message" => "Invalid input request for from date!")));
        if (!isset($_POST["to-date"])) die(json_encode(array("success" => false, "message" => "Invalid input request for to date!")));
        if (!isset($_POST["report-by"])) die(json_encode(array("success" => false, "message" => "Invalid input request for filter by!")));

        if ((!empty($_POST["from-date"]) && empty($_POST["to-date"])) || (!empty($_POST["to-date"]) && empty($_POST["from-date"])))
            die(json_encode(array("success" => false, "message" => "Date range (From - To) not set!")));

        $_data = $expose->validateText($_POST["report-by"]);
        $result = $admin->fetchFormPurchasesGroupReport($_POST);
        if (empty($result)) die(json_encode(array("success" => false, "message" => "No result found for given parameters!")));
        die(json_encode(array("success" => true, "message" => $result)));
    }

    // fetch group sales data
    elseif ($_GET["url"] == "group-sales-report-list") {
        if (!isset($_POST["_dataI"]) || empty($_POST["_dataI"])) die(json_encode(array("success" => false, "message" => "Invalid input request!")));
        if (!isset($_POST["from-date"])) die(json_encode(array("success" => false, "message" => "Invalid input request for from date!")));
        if (!isset($_POST["to-date"])) die(json_encode(array("success" => false, "message" => "Invalid input request for to date!")));
        if (!isset($_POST["report-by"])) die(json_encode(array("success" => false, "message" => "Invalid input request for filter by!")));

        if ((!empty($_POST["from-date"]) && empty($_POST["to-date"])) || (!empty($_POST["to-date"]) && empty($_POST["from-date"])))
            die(json_encode(array("success" => false, "message" => "Date range (From - To) not set!")));

        $_dataI = $expose->validateNumber($_POST["_dataI"]);
        $result = $admin->fetchFormPurchasesGroupReportInfo($_POST);
        if (empty($result)) die(json_encode(array("success" => false, "message" => "No result found for given parameters!")));
        die(json_encode(array("success" => true, "message" => $result)));
    }

    // download PDF
    elseif ($_GET["url"] == "download-file") {
        $result = $admin->prepareDownloadQuery($_POST);
        if (!$result) die(json_encode(array("success" => false, "message" => "Fatal error: server generated error!")));
        die(json_encode(array("success" => true, "message" => "successfully!")));
    } else if ($_GET["url"] == "general-download") {
    }

    // backup database
    elseif ($_GET["url"] == "backup-data") {
        $dbs = ["rmu_admissions"];
        $user = "root";
        $pass = "";
        $host = "localhost";

        if (!file_exists("../Backups")) mkdir("../Backups");

        foreach ($dbs as $db) {
            if (!file_exists("../Backups/$db")) mkdir("../Backups/$db");
            $file_name = $db . "_" . date("F_d_Y") . "@" . date("g_ia") . uniqid("_", false);
            $folder = "../Backups/$db/$file_name" . ".sql";
            $d = exec("mysqldump --user={$user} --password={$pass} --host={$host} {$db} --result-file={$folder}", $output);
            die(json_encode(array("success" => true, "message" => $output)));
        }
    }

    // reset password
    elseif ($_GET["url"] == "reset-password") {
        if (!isset($_POST["currentPassword"]) || empty($_POST["currentPassword"]))
            die(json_encode(array("success" => false, "message" => "Current password field is required!")));
        if (!isset($_POST["newPassword"]) || empty($_POST["newPassword"]))
            die(json_encode(array("success" => false, "message" => "New password field is required!")));
        if (!isset($_POST["renewPassword"]) || empty($_POST["renewPassword"]))
            die(json_encode(array("success" => false, "message" => "Retype new password field is required!")));

        $currentPass = $expose->validatePassword($_POST["currentPassword"]);
        $newPass = $expose->validatePassword($_POST["newPassword"]);
        $renewPass = $expose->validatePassword($_POST["renewPassword"]);

        if ($newPass !== $renewPass) die(json_encode(array("success" => false, "message" => "New password entry mismatched!")));

        $userDetails = $admin->verifySysUserExistsByID($_SESSION["user"]);
        if (empty($userDetails)) die(json_encode(array("success" => false, "message" => "Failed to verify user account!")));

        $result = $admin->verifyAdminLogin($userDetails[0]["user_name"], $currentPass);
        if (!$result) die(json_encode(array("success" => false, "message" => "Incorrect current password!")));

        $changePassword = $admin->resetUserPassword($_SESSION["user"], $newPass);
        die(json_encode($changePassword));
    }

    // admit an applicant to a particular programme and generate admission letter
    elseif ($_GET["url"] == "program-availability") {
        if (!isset($_POST["app-prog-check"]) || empty($_POST["app-prog-check"]))
            die(json_encode(array("success" => false, "message" => "No program provided!")));
        if (!isset($_POST["app-stream-check"]) || empty($_POST["app-stream-check"]))
            die(json_encode(array("success" => false, "message" => "No application stream provided!")));
        die(json_encode($admin->checkProgramStreamAvailability($_POST["app-prog-check"], $_POST["app-stream-check"])));
    }

    // admit an applicant to a particular programme and generate admission letter
    elseif ($_GET["url"] == "admit-individual-applicant") {

        if (!isset($_POST["app-prog-id-check"]) || empty($_POST["app-prog-id-check"]))
            die(json_encode(array("success" => false, "message" => "No program provided!")));

        if (!isset($_POST["app-login-check"]) || empty($_POST["app-login-check"]))
            die(json_encode(array("success" => false, "message" => "No match found for this applicant!")));

        if (!isset($_POST["app-stream-check"]) || empty($_POST["app-stream-check"]))
            die(json_encode(array("success" => false, "message" => "No stream provided for this applicant!")));

        if (!isset($_POST["app-level-admit-check"]) || empty($_POST["app-level-admit-check"]))
            die(json_encode(array("success" => false, "message" => "No level provided for this applicant!")));

        if (!isset($_POST["app-email-check"]))
            die(json_encode(array("success" => false, "message" => "Choose an option to send email to applicant or not!")));

        if (!isset($_POST["app-sms-check"]))
            die(json_encode(array("success" => false, "message" => "Choose an option to send SMS to applicant or not!")));

        $setting = $admin->fetchSettingByName('direct_admit');

        if (!empty($setting) && strtolower($setting[0]["value"]) == 'false') {
            if ($admin->shortlistApplicant($_POST["app-login-check"], $_POST["app-prog-id-check"], $_POST["app-stream-check"], $_POST["app-level-admit-check"], $_POST["app-email-check"], $_POST["app-sms-check"])) {
                die(json_encode(array("success" => true, "message" => "Applicant shortlisted successfully!")));
            } else {
                die(json_encode(array("success" => false, "message" => "Failed to shortlisted applicant!")));
            }
        } else {
            die(json_encode($admin->admitIndividualApplicant($_POST["app-login-check"], $_POST["app-prog-id-check"], $_POST["app-stream-check"], $_POST["app-level-admit-check"], $_POST["app-email-check"], $_POST["app-sms-check"])));
        }
    }

    //
    elseif ($_GET["url"] == "shortlisted-application") {
        try {

            if (!isset($_POST['_FFToken']) || $_POST['_FFToken'] !== $_SESSION['_shortlistedFormToken']) {
                throw new Exception("Invalid security token!");
            }

            if (empty($_POST["action"])) {
                throw new Exception("Action parameter is required!");
            }

            if (empty($_POST["app-login"]) || !is_array($_POST["app-login"])) {
                throw new Exception("Please select at least one application!");
            }

            $app_logins = array_map('trim', $_POST["app-login"]);
            $app_logins = array_filter($app_logins);

            if (empty($app_logins)) {
                throw new Exception("Invalid application data provided!");
            }

            $result = match ($_POST["action"]) {
                'approve' => $admin->approveShortlistedApplications($app_logins),
                'decline' => $admin->declineShortlistedApplications($app_logins),
                default => throw new Exception("Invalid action specified!"),
            };

            die(json_encode($result));
        } catch (Exception $e) {
            die(json_encode(["success" => false, "message" => $e->getMessage()]));
        }
    }

    // decline applicant admission
    elseif ($_GET["url"] == "decline-individual-applicant") {
        if (!isset($_POST["app-login"]) || empty($_POST["app-login"]))
            die(json_encode(array("success" => false, "message" => "There no match for this applicant in database!")));
        die(json_encode($admin->declineIndividualApplicant($_POST["app-login"])));
    }

    // Send admission letter to applicant
    elseif ($_GET["url"] == "send-admission-files") {
        if (!isset($_POST["app-login"]) || empty($_POST["app-login"]))
            die(json_encode(array("success" => false, "message" => "There no match for this applicant in database!")));
        if (!isset($_FILES["send-files"]) || empty($_FILES["send-files"]))
            die(json_encode(array("success" => false, "message" => "Invalid request!")));
        if ($_FILES["send-files"]['error'])
            die(json_encode(array("success" => false, "message" => "Failed to upload file!")));
        die(json_encode($admin->sendAdmissionFiles($_POST["app-login"], $_FILES["send-files"])));
    }

    // Enroll applicant
    elseif ($_GET["url"] == "enroll-applicant") {
        if (!isset($_POST["app-login"]) || empty($_POST["app-login"]))
            die(json_encode(array("success" => false, "message" => "There no match for this applicant in database!")));
        if (!isset($_POST["app-prog"]) || empty($_POST["app-prog"]))
            die(json_encode(array("success" => false, "message" => "Please choose a programme!")));
        if (!isset($_POST["app-level"]) || empty($_POST["app-level"]))
            die(json_encode(array("success" => false, "message" => "Please choose a level!")));
        if (!isset($_POST["app-duration"]) || empty($_POST["app-duration"]))
            die(json_encode(array("success" => false, "message" => "Please choose a level!")));
        die(json_encode($admin->enrollApplicant($_POST["app-login"], $_POST["app-prog"], $_POST["app-level"], $_POST["app-duration"])));
    }

    // Set student's class and courses
    elseif ($_GET["url"] == "set-student-courses") {
        if (!isset($_POST["class"]) || empty($_POST["class"]))
            die(json_encode(array("success" => false, "message" => "Missing parameter in request: class code!")));
        if (!isset($_POST["program"]) || empty($_POST["program"]))
            die(json_encode(array("success" => false, "message" => "Missing parameter in request: program id!")));

        die(json_encode($admin->setStudentCourses($_POST["class"], $_POST["program"])));
    }

    //
    elseif ($_GET["url"] == "unenroll-applicant") {
        if (!isset($_POST["app-login"]) || empty($_POST["app-login"]))
            die(json_encode(array("success" => false, "message" => "There no match for this applicant in database!")));
        if ($admin->updateApplicationStatus($_POST["app-login"], "enrolled", 0)) die(json_encode(array("success" => true)));
        die(json_encode(array("success" => false, "message" => "Failed to updated enrollment status!")));
    }

    ///
    elseif ($_GET["url"] == "export-excel") {
        $t = new DownloadAllExcelDataController($_POST["action"]);
        $file = $t->generateFile();
        $t->downloadFile($file);
        die(json_encode(array("success" => true)));
    }

    //
    else if ($_GET["url"] == "generateNewAPIKeys") {
        if (!isset($_POST["__generateAPIKeys"]) || empty($_POST["__generateAPIKeys"]))
            die(json_encode(array("success" => false, "message" => "Invalid request received!")));

        die(json_encode($admin->generateAPIKeys($_SESSION["vendor_id"])));
    }

    // Unsubmit application
    else if ($_GET["url"] == "unsubmit-application") {
        if (!isset($_POST["app"]) || empty($_POST["app"]))
            die(json_encode(array("success" => false, "message" => "Invalid request received!")));
        die(json_encode($admin->unsubmitApplication($_POST["app"])));
    }

    //
    elseif ($_GET["url"] == "program-info") {
        if (!isset($_POST["prog"]) || empty($_POST["prog"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field")));
        }
        $rslt = $admin->fetchAllFromProgramByName($_POST["prog"]);
        if (!$rslt) die(json_encode(array("success" => false, "message" => "Failed to fetch program's details for this applicant")));
        die(json_encode(array("success" => true, "message" => $rslt)));
    }

    //Departments

    elseif ($_GET["url"] == "fetch-department") {
        if (!isset($_POST["department"]) || empty($_POST["department"])) {
            die(json_encode(array("success" => false, "message" => "Department id is required!")));
        }
        die(json_encode($program->fetch('id', $_POST["department"])));
    } elseif ($_GET["url"] == "add-department") {
        if (!isset($_POST["name"]) || empty($_POST["name"])) {
            die(json_encode(array("success" => false, "message" => "Department name is required!")));
        }
        if (!isset($_POST["hod"]) || empty($_POST["hod"])) {
            die(json_encode(array("success" => false, "message" => "Department hod is required!")));
        }
        die(json_encode($department->add($_POST)));
    } elseif ($_GET["url"] == "update-department") {
        if (!isset($_POST["department"]) || empty($_POST["department"])) {
            die(json_encode(array("success" => false, "message" => "Department id is required!")));
        }
        if (!isset($_POST["name"]) || empty($_POST["name"])) {
            die(json_encode(array("success" => false, "message" => "Department name is required!")));
        }
        if (!isset($_POST["hod"]) || empty($_POST["hod"])) {
            die(json_encode(array("success" => false, "message" => "Department hod is required!")));
        }
        die(json_encode($department->update($_POST)));
    } elseif ($_GET["url"] == "archive-department") {
        if (!isset($_POST["department"]) || empty($_POST["department"])) {
            die(json_encode(array("success" => false, "message" => "Department id is required!")));
        }
        die(json_encode($department->archive($_POST["department"])));
    } elseif ($_GET["url"] == "delete-department") {
        if (!isset($_POST["department"]) || empty($_POST["department"])) {
            die(json_encode(array("success" => false, "message" => "Department id is required!")));
        }
        die(json_encode($department->delete($_POST["department"])));
    } elseif ($_GET["url"] == "total-department") {
        die(json_encode($department->total($_POST["archived"])));
    }

    //programs

    elseif ($_GET["url"] == "fetch-staff") {
        if (!isset($_POST["staff"]) || empty($_POST["staff"])) {
            die(json_encode(array("success" => false, "message" => "Staff id is required!")));
        }
        die(json_encode($staff->fetch($_POST["key"], $_POST["value"], $_POST["archived"])));
    } elseif ($_GET["url"] == "add-staff") {
        if (!isset($_POST["name"]) || empty($_POST["name"])) {
            die(json_encode(array("success" => false, "message" => "Staff name is required!")));
        }
        if (!isset($_POST["hod"]) || empty($_POST["hod"])) {
            die(json_encode(array("success" => false, "message" => "Staff hod is required!")));
        }
        die(json_encode($staff->add($_POST)));
    } elseif ($_GET["url"] == "update-staff") {
        if (!isset($_POST["staff"]) || empty($_POST["staff"])) {
            die(json_encode(array("success" => false, "message" => "Staff id is required!")));
        }
        if (!isset($_POST["name"]) || empty($_POST["name"])) {
            die(json_encode(array("success" => false, "message" => "Staff name is required!")));
        }
        if (!isset($_POST["hod"]) || empty($_POST["hod"])) {
            die(json_encode(array("success" => false, "message" => "Staff hod is required!")));
        }
        die(json_encode($staff->update($_POST, $_POST["staff"])));
    } elseif ($_GET["url"] == "archive-staff") {
        if (!isset($_POST["staff"]) || empty($_POST["staff"])) {
            die(json_encode(array("success" => false, "message" => "Staff id is required!")));
        }
        die(json_encode($staff->archive($_POST["staff"])));
    } elseif ($_GET["url"] == "delete-staff") {
        if (!isset($_POST["staff"]) || empty($_POST["staff"])) {
            die(json_encode(array("success" => false, "message" => "Staff id is required!")));
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
        if (!isset($_POST["code"]) || empty($_POST["code"])) {
            die(json_encode(array("success" => false, "message" => "Course code is required!")));
        }
        die(json_encode(array("success" => true, "data" => $course->fetch("code", $_POST["code"]))));
    }
    //add
    elseif ($_GET["url"] == "add-course") {
        if (!isset($_POST["code"]) || empty($_POST["code"])) {
            die(json_encode(array("success" => false, "message" => "Course code is required!")));
        }
        if (!isset($_POST["name"]) || empty($_POST["name"])) {
            die(json_encode(array("success" => false, "message" => "Course name is required!")));
        }
        if (!isset($_POST["creditHours"]) || empty($_POST["creditHours"])) {
            die(json_encode(array("success" => false, "message" => "Course credit hours is required!")));
        }
        if (!isset($_POST["contactHours"]) || empty($_POST["contactHours"])) {
            die(json_encode(array("success" => false, "message" => "Course contact hours is required!")));
        }
        if (!isset($_POST["semester"]) || empty($_POST["semester"])) {
            die(json_encode(array("success" => false, "message" => "Course semester is required!")));
        }
        if (!isset($_POST["level"]) || empty($_POST["level"])) {
            die(json_encode(array("success" => false, "message" => "Course level is required!")));
        }
        if (!isset($_POST["category"]) || empty($_POST["category"])) {
            die(json_encode(array("success" => false, "message" => "Course category is required!")));
        }
        if (!isset($_POST["department"]) || empty($_POST["department"])) {
            die(json_encode(array("success" => false, "message" => "Course department is required!")));
        }
        die(json_encode($course->add($_POST)));
    }
    //edit
    elseif ($_GET["url"] == "edit-course") {
        if (!isset($_POST["code"]) || empty($_POST["code"])) {
            die(json_encode(array("success" => false, "message" => "Course code is required!")));
        }
        if (!isset($_POST["name"]) || empty($_POST["name"])) {
            die(json_encode(array("success" => false, "message" => "Course name is required!")));
        }
        if (!isset($_POST["creditHours"]) || empty($_POST["creditHours"])) {
            die(json_encode(array("success" => false, "message" => "Course credit hours is required!")));
        }
        if (!isset($_POST["contactHours"]) || empty($_POST["contactHours"])) {
            die(json_encode(array("success" => false, "message" => "Course contact hours is required!")));
        }
        if (!isset($_POST["semester"]) || empty($_POST["semester"])) {
            die(json_encode(array("success" => false, "message" => "Course semester is required!")));
        }
        if (!isset($_POST["level"]) || empty($_POST["level"])) {
            die(json_encode(array("success" => false, "message" => "Course level is required!")));
        }
        if (!isset($_POST["category"]) || empty($_POST["category"])) {
            die(json_encode(array("success" => false, "message" => "Course category is required!")));
        }
        if (!isset($_POST["department"]) || empty($_POST["department"])) {
            die(json_encode(array("success" => false, "message" => "Course department is required!")));
        }
        die(json_encode($course->update($_POST)));
    }
    //archive
    elseif ($_GET["url"] == "archive-course") {
        if (!isset($_POST["code"]) || empty($_POST["code"])) {
            die(json_encode(array("success" => false, "message" => "Course code is required!")));
        }
        die(json_encode($course->archive($_POST["code"])));
    }
    //delete
    elseif ($_GET["url"] == "delete-course") {
        if (!isset($_POST["code"]) || empty($_POST["code"])) {
            die(json_encode(array("success" => false, "message" => "Course code is required!")));
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
        if (!isset($_FILES["uploadCourseFile"]) || empty($_FILES["uploadCourseFile"])) {
            die(json_encode(array("success" => false, "message" => "Invalid request!")));
        }
        if ($_FILES["uploadCourseFile"]['error']) {
            die(json_encode(array("success" => false, "message" => "Failed to upload file!")));
        }

        $excelData = new UploadExcelDataController($_FILES["uploadCourseFile"], 4, 0);
        $result = $excelData->run('course');
        die(json_encode($result));
    }

    // fee structure
    elseif ($_GET["url"] == "fetch-fee-structure") {
        if (!isset($_POST["fee_structure"]) || empty($_POST["fee_structure"])) {
            die(json_encode(array("success" => false, "message" => "Fee structure is required!")));
        }
        die(json_encode(array("success" => true, "data" => $fee_structure->fetch("id", $_POST["fee_structure"]))));
    } elseif ($_GET["url"] == "add-fee-structure") {
        if (!isset($_POST["program"]) || empty($_POST["program"])) {
            die(json_encode(array("success" => false, "message" => "Program is required!")));
        }
        if (!isset($_POST["type"]) || empty($_POST["type"])) {
            die(json_encode(array("success" => false, "message" => "Fee type is required!")));
        }
        if (!isset($_POST["category"]) || empty($_POST["category"])) {
            die(json_encode(array("success" => false, "message" => "Fee category is required!")));
        }
        die(json_encode($fee_structure->add($_POST)));
    } elseif ($_GET["url"] == "update-fee-structure") {
        if (!isset($_POST["fee_structure"]) || empty($_POST["fee_structure"])) {
            die(json_encode(array("success" => false, "message" => "Fee structure is required!")));
        }
        if (!isset($_POST["program"]) || empty($_POST["program"])) {
            die(json_encode(array("success" => false, "message" => "Program is required!")));
        }
        if (!isset($_POST["type"]) || empty($_POST["type"])) {
            die(json_encode(array("success" => false, "message" => "Fee type is required!")));
        }
        if (!isset($_POST["category"]) || empty($_POST["category"])) {
            die(json_encode(array("success" => false, "message" => "Fee category is required!")));
        }
        if (!isset($_POST["member_amount"]) || empty($_POST["member_amount"])) {
            die(json_encode(array("success" => false, "message" => "Member amount is required!")));
        }
        if (!isset($_POST["non_member_amount"]) || empty($_POST["non_member_amount"])) {
            die(json_encode(array("success" => false, "message" => "Non member amount is required!")));
        }
        die(json_encode($fee_structure->update($_POST)));
    } elseif ($_GET["url"] == "archive-fee-structure") {
        if (!isset($_POST["fee_structure"]) || empty($_POST["fee_structure"])) {
            die(json_encode(array("success" => false, "message" => "Fee structure is required!")));
        }
        die(json_encode($fee_structure->archive($_POST["fee_structure"])));
    } elseif ($_GET["url"] == "delete-fee-structure") {
        if (!isset($_POST["fee_structure"]) || empty($_POST["fee_structure"])) {
            die(json_encode(array("success" => false, "message" => "Fee structure is required!")));
        }
        die(json_encode($fee_structure->delete($_POST["fee_structure"])));
    } elseif ($_GET["url"] == "total-fee-structure") {
        die(json_encode($fee_structure->fetch($_POST["key"], $_POST["value"], $_POST["archived"])));
    }

    // Fee Items
    elseif ($_GET["url"] == "fetch-fee-structure-item") {
        if (!isset($_POST["fee_structure"]) || empty($_POST["fee_structure"])) {
            die(json_encode(array("success" => false, "message" => "Fee structure is required!")));
        }
        die(json_encode(array("success" => true, "data" => $fee_structure_item->fetch("fee", $_POST["fee_structure"]))));
    } elseif ($_GET["url"] == "add-fee-structure-item") {
        if (!isset($_POST["fee_structure"]) || empty($_POST["fee_structure"])) {
            die(json_encode(array("success" => false, "message" => "Fee structure is required!")));
        }
        if (!isset($_POST["items"]) || empty($_POST["items"])) {
            die(json_encode(array("success" => false, "message" => "A fee item is required!")));
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

    if ($_GET["url"] == "adp-form") {
        if (!isset($_PUT["adp_key"]) || empty($_PUT["adp_key"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field")));
        }

        $rslt = $admin->openOrCloseAdmissionPeriod($_PUT["adp_key"], 0);
        if (!$rslt) die(json_encode(array("success" => false, "message" => "Failed to delete programme!")));
        die(json_encode(array("success" => true, "message" => "Successfully deleted programme!")));
    }

    // All DELETE request will be sent here
} else if ($_SERVER['REQUEST_METHOD'] == "DELETE") {
    parse_str(file_get_contents("php://input"), $_DELETE);

    if ($_GET["url"] == "form-price") {
        if (!isset($_DELETE["form_key"]) || empty($_DELETE["form_key"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field")));
        }

        $rslt = $admin->deleteFormPrice($_DELETE["form_key"]);
        if (!$rslt) die(json_encode(array("success" => false, "message" => "Failed to delete form price!")));
        die(json_encode(array("success" => true, "message" => "Successfully deleted form price!")));
    }

    if ($_GET["url"] == "vendor-form") {
        if (!isset($_DELETE["vendor_key"]) || empty($_DELETE["vendor_key"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field")));
        }

        $rslt = $admin->deleteVendor($_DELETE["vendor_key"]);
        if (!$rslt) die(json_encode(array("success" => false, "message" => "Failed to delete form price!")));
        die(json_encode(array("success" => true, "message" => "Successfully deleted form price!")));
    }

    if ($_GET["url"] == "prog-form") {
        if (!isset($_DELETE["prog_key"]) || empty($_DELETE["prog_key"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field")));
        }

        $rslt = $admin->deleteProgramme($_DELETE["prog_key"]);
        if (!$rslt)  die(json_encode(array("success" => false, "message" => "Failed to delete programme!")));
        die(json_encode(array("success" => true, "message" => "Successfully deleted programme!")));
    }

    if ($_GET["url"] == "user-form") {
        if (!isset($_DELETE["user_key"]) || empty($_DELETE["user_key"])) {
            die(json_encode(array("success" => false, "message" => "Missing input field")));
        }

        $rslt = $admin->deleteSystemUser($_DELETE["user_key"]);
        if (!$rslt) die(json_encode(array("success" => false, "message" => "Failed to delete user account!")));
        die(json_encode(array("success" => true, "message" => "Successfully deleted user account!")));
    }
} else {
    http_response_code(405);
}
