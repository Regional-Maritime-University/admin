<?php

namespace Src\Controller;

use Src\Controller\ApplicantEvaluator;
use Exception;
use Src\System\DatabaseMethods;
use Src\Controller\ExposeDataController;
use Src\Controller\PaymentController;
use PhpOffice\PhpWord\TemplateProcessor;

class AdminController
{
    private $dm = null;
    private $expose = null;
    private $pay = null;

    public function __construct($db, $user, $pass)
    {
        $this->dm = new DatabaseMethods($db, $user, $pass);
        $this->expose = new ExposeDataController($db, $user, $pass);
        $this->pay = new PaymentController($db, $user, $pass);
    }

    public function processVendorPay($data)
    {
        return $this->pay->vendorPaymentProcess($data);
    }

    public function fetchVendorUsernameByUserID(int $user_id)
    {
        $query = "SELECT user_name FROM sys_users AS su, vendor_details AS vd WHERE su.id = vd.user_id AND vd.id = :ui";
        return $this->dm->getData($query, array(':ui' => $user_id));
    }

    public function resetUserPassword($user_id, $password)
    {
        // Hash password
        $hashed_pw = password_hash($password, PASSWORD_DEFAULT);
        $query = "UPDATE sys_users SET `password` = :pw WHERE id = :id";
        $query_result = $this->dm->getData($query, array(":id" => $user_id, ":pw" => $hashed_pw));

        if ($query_result) {
            $this->logActivity(
                $_SESSION["user"],
                "UPDATE",
                "{$_SESSION["user"]} Updated user their account's password"
            );
            return array("success" => true, "message" => "Account's password reset was successful!");
        }
        return array("success" => false, "message" => "Failed to reset user account password!");
    }

    public function verifyAdminLogin($username, $password)
    {
        $sql = "SELECT * FROM `sys_users` WHERE `user_name` = :u";
        $data = $this->dm->getData($sql, array(':u' => $username));
        if (!empty($data)) {
            if (password_verify($password, $data[0]["password"])) {
                return $data;
            }
        }
        return 0;
    }

    public function getAcademicPeriod($admin_period)
    {
        $query = "SELECT YEAR(`start_date`) AS start_year, YEAR(`end_date`) AS end_year, `info`, `intake`, `active`, `closed` 
                FROM admission_period WHERE id = :ai";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function getCurrentAdmissionPeriodID()
    {
        return $this->dm->getID("SELECT `id` FROM `admission_period` WHERE `active` = 1");
    }

    public function fetchPrograms(int $type = 0, $prog = "")
    {
        if ($type != 0 && !empty($prog)) {
            $prog_code = match ($prog) {
                "MASTERS" => "MSC",
                "UPGRADERS" => "UPGRADE",
                "DEGREE" => "BSC",
                "DIPLOMA" => "DIPLOMA",
                "VOCATIONAL/PROFESSIONAL COURSES" => "SHORT"
            };
            $query = "SELECT * FROM programs WHERE `type` = :t AND `code` = :p";
            $param = array(':t' => $type, ':p' => $prog_code);
        } else {
            $query = "SELECT * FROM programs";
            $param = array();
        }
        return $this->dm->getData($query, $param);
    }

    public function getAvailableFormsExceptType($type)
    {
        return $this->dm->getData("SELECT * FROM `forms` WHERE `form_category` <> :t", array(":t" => $type));
    }

    public function getAvailableForms()
    {
        return $this->dm->getData("SELECT * FROM `forms`");
    }

    public function getFormByFormID($form_id)
    {
        return $this->dm->getData("SELECT * FROM `forms` WHERE id = :i", array(":i" => $form_id));
    }

    public function getFormCategories()
    {
        return $this->dm->getData("SELECT * FROM `form_categories`");
    }

    public function fetchUserName($user_id)
    {
        $sql = "SELECT CONCAT(SUBSTRING(`first_name`, 1, 1), '. ' , `last_name`) AS `userName` 
                FROM `sys_users` WHERE `id` = :u";
        return $this->dm->getData($sql, array(':u' => $user_id));
    }

    public function fetchFullName($user_id)
    {
        $sql = "SELECT CONCAT(`first_name`, ' ' , `last_name`) AS `fullName`, 
                user_name AS email_address, `role` AS user_role 
                FROM `sys_users` WHERE `id` = :u";
        return $this->dm->getData($sql, array(':u' => $user_id));
    }

    public function logActivity(int $user_id, $operation, $description)
    {
        $query = "INSERT INTO `activity_logs`(`user_id`, `operation`, `description`) VALUES (:u,:o,:d)";
        $params = array(":u" => $user_id, ":o" => $operation, ":d" => $description);
        $this->dm->inputData($query, $params);
    }
    // For admin settings


    /**
     * CRUD for form price
     */

    public function fetchAllFormPriceDetails()
    {
        $query = "SELECT f.id, f.name AS form_name, fc.name AS form_type_name, f.amount 
                FROM form_categories AS fc, forms AS f WHERE fc.id = f.form_category";
        return $this->dm->getData($query);
    }

    public function fetchFormPrice($form_price_id)
    {
        $query = "SELECT fp.id AS fp_id, ft.id AS ft_id, ft.name AS ft_name, fp.name AS fp_name, fp.amount 
                FROM form_categories AS ft, forms AS fp WHERE ft.id = fp.form_category AND fp.id = :i";
        return $this->dm->getData($query, array(":i" => $form_price_id));
    }

    public function addFormPrice($form_category, $form_name, $form_price)
    {
        $query = "INSERT INTO forms (form_category, `name`, amount) VALUES(:ft, :fn, :fp)";
        $params = array(":ft" => $form_category, ":fn" => $form_name, ":fp" => $form_price);
        $query_result = $this->dm->inputData($query, $params);

        if ($query_result)
            $this->logActivity(
                $_SESSION["user"],
                "INSERT",
                "Added new {$form_name} form costing {$form_price} to form type {$form_category}"
            );
        return $query_result;
    }

    public function updateFormPrice(int $form_id, $form_category, $form_name, $form_price)
    {
        $query = "UPDATE forms SET amount = :fp, form_category = :ft, `name` = :fn WHERE id = :i";
        $params = array(":i" => $form_id, ":ft" => $form_category, ":fn" => $form_name, ":fp" => $form_price);
        $query_result = $this->dm->inputData($query, $params);

        if ($query_result)
            $this->logActivity(
                $_SESSION["user"],
                "UPDATE",
                "Updated {$form_name} form costing {$form_price} to form type {$form_category}"
            );
        return $query_result;
    }

    public function deleteFormPrice($form_price_id)
    {
        $query = "DELETE FROM forms WHERE id = :i";
        $params = array(":i" => $form_price_id);
        $query_result = $this->dm->inputData($query, $params);

        if ($query_result)
            $this->logActivity(
                $_SESSION["user"],
                "DELETE",
                "Deleted form with id {$form_price_id}"
            );
        return $query_result;
    }

    /**
     * CRUD for vendor
     */

    public function fetchAllVendorsMainBranch()
    {
        return $this->dm->getData("SELECT * FROM vendor_details WHERE `type` <> 'ONLINE' AND branch = 'MAIN'");
    }

    public function fetchVendorsBranches($company)
    {
        return $this->dm->getData("SELECT * FROM vendor_details WHERE `company` = :c", array(":c" => $company));
    }

    public function fetchAllVendorDetails()
    {
        return $this->dm->getData("SELECT * FROM vendor_details WHERE `type` <> 'ONLINE'");
    }

    public function fetchVendor(int $vendor_id)
    {
        $query = "SELECT vd.*, su.first_name, su.last_name, su.user_name 
                    FROM vendor_details AS vd, sys_users AS su WHERE vd.id = :i AND vd.user_id = su.id";
        return $this->dm->inputData($query, array(":i" => $vendor_id));
    }

    public function fetchVendorDataByUserID(int $user_id)
    {
        $query = "SELECT vd.*, su.first_name, su.last_name, su.user_name 
                    FROM vendor_details AS vd, sys_users AS su WHERE su.id = :i AND vd.user_id = su.id";
        return $this->dm->inputData($query, array(":i" => $user_id));
    }

    public function fetchVendorSubBranchesGrp($company)
    {
        $query = "SELECT * FROM vendor_details WHERE company = :c AND 
                branch <> 'MAIN' AND `type` <> 'ONLINE' GROUP BY `branch`";
        return $this->dm->inputData($query, array(":c" => $company));
    }

    public function fetchVendorSubBranches($company)
    {
        $query = "SELECT * FROM vendor_details WHERE branch = :b AND 
                branch <> 'MAIN' AND `type` <> 'ONLINE'";
        return $this->dm->inputData($query, array(":b" => $company));
    }

    public function verifyVendorByCompanyAndBranch($company, $branch)
    {
        $query = "SELECT `id` FROM `vendor_details` WHERE `company` = :c AND `branch` = :b";
        return $this->dm->inputData($query, array(":c" => $company, ":b" => $branch));
    }

    public function verifySysUserExistsByID($user_id)
    {
        $query = "SELECT * FROM `sys_users` WHERE `id` = :u";
        return $this->dm->inputData($query, array(":u" => $user_id));
    }

    public function saveDataFile($fileObj)
    {
        $allowedFileType = [
            'application/vnd.ms-excel',
            'text/xls',
            'text/xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];

        if (!in_array($fileObj["type"], $allowedFileType)) {
            return array("success" => false, "message" => "Invalid file type. Please choose an excel file!");
        }

        if ($fileObj['error'] == UPLOAD_ERR_OK) {

            // Create a unique file name
            $name = time() . '-' . 'awaiting.xlsx';

            // Create the full path to the file
            $targetPath = UPLOAD_DIR . "/branches/" . $name;

            // Delete file if exsists
            if (file_exists($targetPath)) {
                unlink($targetPath);
            }

            // Move the file to the target directory
            if (!move_uploaded_file($fileObj['tmp_name'], $targetPath))
                return array("success" => false, "message" => "Failed to upload file!");
            return array("success" => true, "message" => $targetPath);
        }
        return array("success" => false, "message" => "Error: Invalid file object!");
    }

    public function uploadCompanyBranchesData($mainBranch, $fileObj)
    {
        // save file to uploads folder
        $file_upload_msg = $this->saveDataFile($fileObj);
        if (!$file_upload_msg["success"]) return $file_upload_msg;

        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $spreadSheet = $reader->load($file_upload_msg["message"]);
        $excelSheet = $spreadSheet->getActiveSheet();
        $spreadSheetArray = $excelSheet->toArray();

        $startRow = 1;
        $endRow = count($spreadSheetArray);

        $successCount = 0;
        $errorCount = 0;
        $skippedCount = array();

        $privileges = array("select" => 1, "insert" => 1, "update" => 0, "delete" => 0);

        for ($i = $startRow; $i <= $endRow - 1; $i++) {
            $v_branch = $spreadSheetArray[$i][0];
            $v_email = $spreadSheetArray[$i][1];
            $v_phone = $spreadSheetArray[$i][2];
            $v_role = $spreadSheetArray[$i][3];

            if (!$v_branch || !$v_email || !$v_phone) {
                array_push($skippedCount, ($i - 1));
                continue;
            }

            $user_data = array(
                "first_name" => $mainBranch,
                "last_name" => $v_branch,
                "user_name" => $v_email,
                "user_role" => "Vendors",
                "vendor_company" => $mainBranch,
                "vendor_phone" => $v_phone,
                "vendor_branch" => $v_branch,
                "vendor_role" => $v_role
            );

            $vendor_id = time() + $i;
            if ($this->addSystemUser($user_data, $privileges, $vendor_id)) $successCount += 1;
            else $errorCount += 1;
        }
        return array(
            "success" => true,
            "message" => "Successfully added MAIN branch account and {$successCount} other branches with {$errorCount} unsuccessful!. Skipped rows are " . json_encode($skippedCount)
        );
    }

    public function updateVendor($v_id, $v_email, $v_phone)
    {
        $query1 = "UPDATE vendor_details SET `phone_number` = :pn WHERE id = :id";
        $params1 = array(":id" => $v_id, ":pn" => $v_phone);
        if (!$this->dm->inputData($query1, $params1))
            return array("success" => false, "message" => "Failed to updated vendor's account information! [Error code 1]");

        $query2 = "UPDATE sys_users SET `user_name` = :ea WHERE id = :id";
        $params2 = array(":id" => $v_id, ":ea" => $v_email);
        if (!$this->dm->inputData($query2, $params2))
            return array("success" => false, "message" => "Failed to updated vendor's information! [Error code 2]");

        $this->logActivity($_SESSION["user"], "UPDATE", "Updated information for vendor {$v_id}");
        return array("success" => true, "message" => "Successfully updated vendor's account information!");
    }

    public function deleteVendor($vendor_id)
    {
        $vendor_info = $this->fetchVendor($vendor_id);
        $this->deleteSystemUser($vendor_info[0]["user_id"]);
        if ($vendor_info[0]["api_user"] == 1) $this->dm->inputData("DELETE FROM api_users WHERE vendor_id = :i", array(":i" => $vendor_id));
        $query_result2 = $this->dm->inputData("DELETE FROM vendor_details WHERE id = :i", array(":i" => $vendor_id));

        if ($query_result2)
            $this->logActivity(
                $_SESSION["user"],
                "DELETE",
                "Deleted vendor {$vendor_id} information"
            );
        return $query_result2;
    }

    /**
     * CRUD for programme
     */

    public function fetchAllPrograms()
    {
        $query = "SELECT p.`id`, p.`name`, f.name AS `type`, p.`weekend`, p.`group` 
                FROM programs AS p, forms AS f WHERE p.type = f.id";
        return $this->dm->getData($query);
    }

    public function fetchProgramme($prog_id)
    {
        $query = "SELECT p.`id`, p.`name`, f.id AS `type`, p.`weekend`, p.`group` 
                FROM programs AS p, forms AS f WHERE p.type = f.id AND p.id = :i";
        return $this->dm->getData($query, array(":i" => $prog_id));
    }

    public function fetchAllFromProgramByName($prog_name)
    {
        return $this->dm->getData("SELECT * FROM programs WHERE `name` = :n", array(":n" => $prog_name));
    }

    public function fetchAllFromProgramByID($prog_id)
    {
        $query = "SELECT pg.*, dp.`name` AS department_name FROM programs AS pg, departments AS dp 
                    WHERE pg.`id` = :i AND pg.`department` = dp.`id`";
        return $this->dm->getData($query, array(":i" => $prog_id));
    }

    public function fetchAllFromProgramByCode($prog_code)
    {
        return $this->dm->getData("SELECT * FROM programs WHERE `category` = :c", array(":c" => $prog_code));
    }

    public function addProgramme($prog_name, $prog_type, $prog_wkd, $prog_grp)
    {
        $query = "INSERT INTO programs (`name`, `type`, `weekend`, `group`) VALUES(:n, :t, :w, :g)";
        $params = array(":n" => strtoupper($prog_name), ":t" => $prog_type, ":w" => $prog_wkd, ":g" => $prog_grp);
        $query_result = $this->dm->inputData($query, $params);
        if ($query_result)
            $this->logActivity(
                $_SESSION["user"],
                "INSERT",
                "Added new programme {$prog_name} of programme type {$prog_type}"
            );
        return $query_result;
    }

    public function updateProgramme($prog_id, $prog_name, $prog_type, $prog_wkd, $prog_grp)
    {
        $query = "UPDATE programs SET `name` = :n, `type` = :t, `weekend` = :w, `group` = :g WHERE id = :i";
        $params = array(":n" => strtoupper($prog_name), ":t" => $prog_type, ":w" => $prog_wkd, ":g" => $prog_grp, ":i" => $prog_id);
        $query_result = $this->dm->inputData($query, $params);
        if ($query_result)
            $this->logActivity(
                $_SESSION["user"],
                "UPDATE",
                "Updated information for program {$prog_id}"
            );
        return $query_result;
    }

    public function deleteProgramme($prog_id)
    {
        $query = "DELETE FROM programs WHERE id = :i";
        $query_result = $this->dm->inputData($query, array(":i" => $prog_id));
        if ($query_result)
            $this->logActivity(
                $_SESSION["user"],
                "DELETE",
                "Deleted programme {$prog_id}"
            );
        return $query_result;
    }

    /**
     * CRUD for Admission Period
     */

    public function fetchAllAdmissionPeriod()
    {
        return $this->dm->getData("SELECT * FROM admission_period ORDER BY `id` DESC");
    }

    public function fetchCurrentAdmissionPeriod()
    {
        return $this->dm->getData("SELECT * FROM admission_period WHERE `active` = 1");
    }

    public function fetchAdmissionPeriodByID($adp_id)
    {
        $query = "SELECT * FROM admission_period WHERE id = :i";
        return $this->dm->inputData($query, array(":i" => $adp_id));
    }

    public function addAdmissionPeriod($adp_start, $adp_end, $adp_info, $intake)
    {
        $query = "INSERT INTO admission_period (`start_date`, `end_date`, `info`, `intake`) 
                VALUES(:sd, :ed, :i, :t)";
        $params = array(":sd" => $adp_start, ":ed" => $adp_end, ":i" => $adp_info, ":t" => $intake);
        $query_result = $this->dm->inputData($query, $params);
        if (empty($query_result)) return array("success" => false, "message" => "Failed to open new admission period!");
        $this->openOrCloseAdmissionPeriod($this->expose->getCurrentAdmissionPeriodID(), 0);
        $this->openOrCloseAdmissionPeriod($query_result, 1);
        $this->logActivity(
            $_SESSION["user"],
            "INSERT",
            "Added admisiion period  with start date {$adp_start} and end date {$adp_end}"
        );
        return array("success" => true, "message" => "New admission period successfully open!");
    }

    public function updateAdmissionPeriod($adp_id, $adp_end, $adp_info)
    {
        $query = "UPDATE admission_period SET `end_date` = :ed, `info` = :i WHERE id = :id";
        $params = array(":ed" => $adp_end, ":i" => $adp_info, ":id" => $adp_id);
        $query_result = $this->dm->inputData($query, $params);
        if (empty($query_result)) return array("success" => false, "message" => "Failed to update admission information!");
        if ($query_result)
            $this->logActivity(
                $_SESSION["user"],
                "UPDATE",
                "Updated information for admisiion period {$adp_id}"
            );
        return array("success" => true, "message" => "Successfully updated admission information!");
    }

    public function openOrCloseAdmissionPeriod($adp_id, $status): mixed
    {
        $query = "UPDATE admission_period SET active = :s, closed = :c WHERE id = :i";
        $query_result = $this->dm->inputData($query, array(":s" => $status, ":c" => !$status, ":i" => $adp_id));
        if (empty($query_result)) return 0;
        if ($status == 0) $this->logActivity($_SESSION["user"], "UPDATE", "Closed admission with id {$adp_id}");
        else if ($status == 1) $this->logActivity($_SESSION["user"], "UPDATE", "Opened admission with id {$adp_id}");
        return $query_result;
    }


    /**
     * CRUD for user accounts
     */

    public function fetchAllNotVendorSystemUsers()
    {
        return $this->dm->getData("SELECT * FROM `sys_users` WHERE `role` <> 'Vendors'");
    }

    public function fetchAllSystemUsers()
    {
        return $this->dm->getData("SELECT * FROM `sys_users`");
    }

    public function fetchSystemUser($user_id)
    {
        $query = "SELECT u.*, p.`select`, p.`insert`, p.`update`, p.`delete` 
                FROM sys_users AS u, sys_users_privileges AS p 
                WHERE u.`id` = :i AND u.`id` = p.`user_id`";
        return $this->dm->inputData($query, array(":i" => $user_id));
    }

    public function verifySysUserByEmail($email)
    {
        $query = "SELECT `id` FROM `sys_users` WHERE `user_name` = :u";
        return $this->dm->inputData($query, array(":u" => $email));
    }

    public function addSystemUser($user_data, $privileges, $vendor_id = 0)
    {
        // verify if a vendor with this email exists
        if ($this->verifySysUserByEmail($user_data["user_name"])) {
            return array("success" => false, "message" => "This email ({$user_data['user_name']}) is associated with an account!");
        }

        // Generate password
        $password = $this->expose->genVendorPin();

        // Hash password
        $hashed_pw = password_hash($password, PASSWORD_DEFAULT);

        // Create insert query
        $query1 = "INSERT INTO sys_users (`first_name`, `last_name`, `user_name`, `password`, `role`, `type`) VALUES(:fn, :ln, :un, :pw, :rl, :tp)";
        $params1 = array(
            ":fn" => $user_data["first_name"],
            ":ln" => $user_data["last_name"],
            ":un" => $user_data["user_name"],
            ":pw" => $hashed_pw,
            ":rl" => $user_data["user_role"],
            ":tp" => $user_data["user_type"]
        );

        // execute query
        $action1 = $this->dm->inputData($query1, $params1);
        if (!$action1) return array("success" => false, "message" => "Failed to create user account!");

        // verify and get user account info
        $sys_user = $this->verifyAdminLogin($user_data["user_name"], $password);
        if (empty($sys_user)) return array("success" => false, "message" => "Created user account, but failed to verify user account!");

        // Create insert query for user privileges
        $query2 = "INSERT INTO `sys_users_privileges` (`user_id`, `select`,`insert`,`update`,`delete`) 
                VALUES(:ui, :s, :i, :u, :d)";
        $params2 = array(
            ":ui" => $sys_user[0]["id"],
            ":s" => $privileges["select"],
            ":i" => $privileges["insert"],
            ":u" => $privileges["update"],
            ":d" => $privileges["delete"]
        );

        // Execute user privileges 
        $action2 = $this->dm->inputData($query2, $params2);
        if (!$action2) return array("success" => false, "message" => "Failed to create given roles for the user!");

        $subject = "Regional Maritime University - User Account";

        if (strtoupper($user_data["user_role"]) == "VENDORS") {
            if (!$vendor_id) $vendor_id = time();
            $query1 = "INSERT INTO vendor_details (`id`, `type`, `company`, `company_code`, `branch`, `role`, `phone_number`, `user_id`, `api_user`) 
                        VALUES(:id, :tp, :cp, :cc, :bh, :vr, :pn, :ui, :au)";
            $params1 = array(
                ":id" => $vendor_id,
                ":tp" => "VENDOR",
                ":cp" => $user_data["vendor_company"],
                ":cc" => strtoupper($user_data["company_code"]),
                ":bh" => $user_data["vendor_branch"],
                ":vr" => $user_data["vendor_role"],
                ":pn" => $user_data["vendor_phone"],
                ":ui" => $sys_user[0]["id"],
                ":au" => $user_data["api_user"]
            );
            $this->dm->inputData($query1, $params1);
            $subject = "Regional Maritime University - Vendor Account";
        }

        $this->logActivity(
            $_SESSION["user"],
            "INSERT",
            "Added new user account with username/email {$user_data["user_name"]}"
        );

        // Prepare email
        $message = "<p>Hi " . $user_data["first_name"] . " " . $user_data["last_name"] . ", </p></br>";
        $message .= "<p>Your account to access Regional Maritime University's Admissions Portal as a " . $user_data["user_role"] . " was created successfully.</p>";
        $message .= "<p>Find below your Login details.</p></br>";
        $message .= "<p style='font-weight: bold;'>Username: " . $user_data["user_name"] . "</p>";
        $message .= "<p style='font-weight: bold;'>Password: " . $password . "</p></br>";
        $message .= "<div>Please note the following: </div>";
        $message .= "<ol style='color:red; font-weight:bold;'>";
        $message .= "<li>Don't let anyone see your login password</li>";
        $message .= "<li>Access the portal and change your password</li>";
        $message .= "</ol></br>";
        $message .= "<p><a href='https://office.rmuictonline.com'>Click here to access portal</a>.</p>";

        // Send email
        $emailed = $this->expose->sendEmail($user_data["user_name"], $subject, $message);

        // verify email status and return result
        if ($emailed !== 1) return array(
            "success" => false,
            "message" => "Created user account, but failed to send email! Error: " . $emailed
        );

        return array("success" => true, "message" => "Successfully created user account!");
    }

    public function updateSystemUser($data, $privileges)
    {
        $query = "UPDATE sys_users SET `user_name` = :un, `first_name` = :fn, `last_name` = :ln, `role` = :rl, `type` = :tp 
                WHERE id = :id";
        $params = array(
            ":un" => $data["user-email"],
            ":fn" => $data["user-fname"],
            ":ln" => $data["user-lname"],
            ":rl" => $data["user-role"],
            ":tp" => $data["user-type"],
            ":id" => $data["user-id"]
        );
        if ($this->dm->inputData($query, $params)) {
            // Create insert query for user privileges
            $query2 = "UPDATE `sys_users_privileges` SET `select` = :s, `insert` = :i,`update` = :u, `delete`= :d 
                        WHERE `user_id` = :ui";
            $params2 = array(
                ":ui" => $data["user-id"],
                ":s" => $privileges["select"],
                ":i" => $privileges["insert"],
                ":u" => $privileges["update"],
                ":d" => $privileges["delete"]
            );
            // Execute user privileges 
            $action2 = $this->dm->inputData($query2, $params2);
            if (!$action2) return array("success" => false, "message" => "Failed to update user account privileges!");

            $this->logActivity(
                $_SESSION["user"],
                "UPDATE",
                "Updated user {$data["user-id"]} account information and privileges"
            );

            return array("success" => true, "message" => "Successfully updated user account information!");
        }
        return array("success" => false, "message" => "Failed to update user account information!");
    }

    public function changeSystemUserPassword($user_id, $email_addr, $first_name)
    {
        $password = $this->expose->genVendorPin();
        $hashed_pw = password_hash($password, PASSWORD_DEFAULT);
        $query = "UPDATE sys_users SET `password` = :pw WHERE id = :id";
        $params = array(":id" => $user_id, ":pw" => $hashed_pw);
        $query_result = $this->dm->inputData($query, $params);

        if ($query_result) {

            $this->logActivity(
                $_SESSION["user"],
                "UPDATE",
                "Updated user {$user_id} account's password"
            );

            return $query_result;
            $subject = "RMU System User";
            $message = "<p>Hi " . $first_name . ", </p></br>";
            $message .= "<p>Find below your Login details.</p></br>";
            $message .= "<p style='font-weight: bold; font-size: 18px'>Username: " . $email_addr . "</p></br>";
            $message .= "<p style='font-weight: bold; font-size: 18px'>Password: " . $password . "</p></br>";
            $message .= "<p style='color:red; font-weight:bold'>Don't let anyone see your login password</p></br>";
            return $this->expose->sendEmail($email_addr, $subject, $message);
        }
        return 0;
    }

    public function deleteSystemUser($user_id)
    {
        $query = "DELETE FROM sys_users WHERE id = :i";
        $params = array(":i" => $user_id);
        $query_result = $this->dm->inputData($query, $params);
        if ($query_result)
            $this->logActivity(
                $_SESSION["user"],
                "DELETE",
                "Removed user {$user_id} accounts"
            );
        return $query_result;
    }

    // end of setups

    // CRUD for API Users

    public function genVendorAPIKeyPairs(int $length_pin = 8)
    {
        $str_result = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        return substr(str_shuffle($str_result), 0, $length_pin);
    }

    public function fetchVendorAPIData($vendor_id): mixed
    {
        $query = "SELECT au.*, vd.company, vd.company_code FROM api_users AS au, vendor_details AS vd 
                    WHERE au.vendor_id = :vi AND au.vendor_id = vd.id";
        return $this->dm->getData($query, array(":vi" => $vendor_id));
    }

    public function generateAPIKeys($vendor_id): mixed
    {
        $vendorData = $this->fetchVendor($vendor_id);
        if (empty($vendorData)) return array("success" => false, "message" => "Vendor data doesn't exist");
        if ($vendorData[0]["api_user"] == 0) return array("success" => false, "message" => "This vendor account is not allowed to use RMU forms APIs");

        // Generate vendor api username
        $api_username = strtolower($this->genVendorAPIKeyPairs());
        // Generate vendor api password
        $api_password = $this->genVendorAPIKeyPairs(12);
        // Hash password
        $hashed_pw = password_hash($api_password, PASSWORD_DEFAULT);

        $vendorAPIData = $this->fetchVendorAPIData($vendor_id);
        if (empty($vendorAPIData)) $query = "INSERT INTO api_users (`username`, `password`, `vendor_id`) VALUES(:un, :pw, :vi)";
        else $query = "UPDATE api_users SET `username` = :un, `password` = :pw WHERE `vendor_id` = :vi";
        $params = array(":un" => $api_username, ":pw" => $hashed_pw, ":vi" => $vendor_id);

        if ($this->dm->inputData($query, $params))
            return array("success" => true, "message" => array("client_id" => $api_username, "client_secret" => $api_password));

        return array("success" => false, "message" => "Failed to generate new API keys");
    }

    public function fetchAvailableformTypes()
    {
        return $this->dm->getData("SELECT * FROM forms");
    }

    public function getFormTypeName(int $form_id)
    {
        $query = "SELECT * FROM forms WHERE id = :i";
        return $this->dm->getData($query, array(":i" => $form_id));
    }

    public function getApplicantAppNum(int $app_num)
    {
        $query = "SELECT pd.`app_number` FROM `purchase_detail` AS pd, `applicants_login` AS al 
                WHERE pd.`id` = al.`purchase_id` AND al.`id` = :i";
        return $this->dm->getData($query, array(":i" => $app_num));
    }

    public function fetchAllAwaitingApplicationsBS($admin_period)
    {
        $query = "SELECT pd.id AS AdmissionNumber, ab.index_number AS IndexNumber, 
                    ab.month_completed AS ExamMonth, ab.year_completed AS ExamYear, pf.first_prog AS Program 
                FROM 
                    applicants_login AS al, purchase_detail AS pd, admission_period AS ap, 
                    academic_background AS ab, form_sections_chek AS fc, program_info AS pf 
                WHERE 
                    al.id = ab.app_login AND al.purchase_id = pd.id AND ap.id = pd.admission_period AND al.id = pf.app_login AND 
                    fc.app_login = al.id AND fc.`declaration` = 1 AND ab.awaiting_result = 1 AND ap.id = :ai AND 
                    ab.cert_type = 'WASSCE' AND ab.country = 'GHANA' AND 
                    pd.id NOT IN (SELECT admission_number FROM downloaded_awaiting_results) ORDER BY Program";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchAllAwaitingApplicationsBSGrouped($admin_period): mixed
    {
        $query = "SELECT pf.first_prog AS Program 
                FROM 
                    applicants_login AS al, purchase_detail AS pd, admission_period AS ap, 
                    academic_background AS ab, form_sections_chek AS fc, program_info AS pf 
                WHERE 
                    al.id = ab.app_login AND al.purchase_id = pd.id AND ap.id = pd.admission_period AND al.id = pf.app_login AND 
                    fc.app_login = al.id AND fc.`declaration` = 1 AND ab.awaiting_result = 1 AND ap.id = :ai AND 
                    ab.cert_type = 'WASSCE' AND ab.country = 'GHANA' AND 
                    pd.id NOT IN (SELECT admission_number FROM downloaded_awaiting_results) GROUP BY Program ORDER BY Program";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }


    public function saveDownloadedAwaitingResults($data = array()): mixed
    {
        $count = 0;
        foreach ($data as $d) {
            $this->dm->inputData("INSERT INTO downloaded_awaiting_results (`admission_number`) VALUES(:al)", array(":al" => $d["AdmissionNumber"]));
            $count++;
        }
        return $count;
    }

    /**
     * Fetching forms sale data totals
     */

    public function fetchTotalFormsSold($admin_period)
    {
        $query = "SELECT COUNT(pd.id) AS total 
                FROM purchase_detail AS pd, forms AS ft, admission_period AS ap, vendor_details AS v 
                WHERE pd.form_id = ft.id AND pd.admission_period = ap.id AND pd.vendor = v.id AND ap.id = :ai";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchTotalPostgradsFormsSold($admin_period)
    {
        $query = "SELECT COUNT(pd.id) AS total 
        FROM 
            purchase_detail AS pd, forms AS ft, admission_period AS ap, vendor_details AS v  
        WHERE
            pd.form_id = ft.id AND pd.admission_period = ap.id AND 
            pd.vendor = v.id AND ap.id = :ai AND ft.name LIKE '%Post%' OR ft.name LIKE '%Master%'";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchTotalUdergradsFormsSold($admin_period)
    {
        $query = "SELECT COUNT(pd.id) AS total 
        FROM 
            purchase_detail AS pd, forms AS ft, admission_period AS ap, vendor_details AS v  
        WHERE
            pd.form_id = ft.id AND pd.admission_period = ap.id AND 
            pd.vendor = v.id AND ap.id = :ai AND (ft.name LIKE '%Degree%' OR ft.name LIKE '%Diploma%')";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchTotalShortCoursesFormsSold($admin_period)
    {
        $query = "SELECT COUNT(pd.id) AS total 
        FROM 
            purchase_detail AS pd, forms AS ft, admission_period AS ap, vendor_details AS v  
        WHERE
            pd.form_id = ft.id AND pd.admission_period = ap.id AND 
            pd.vendor = v.id AND ap.id = :ai AND ft.name LIKE '%Short%'";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchTotalVendorsFormsSold($admin_period)
    {
        $query = "SELECT COUNT(pd.id) AS total 
        FROM 
            purchase_detail AS pd, forms AS ft, 
            admission_period AS ap, vendor_details AS v  
        WHERE
            pd.form_id = ft.id AND pd.admission_period = ap.id AND 
            pd.vendor = v.id AND ap.id = :ai AND v.vendor_name NOT LIKE '%ONLINE%'";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchTotalOnlineFormsSold($admin_period)
    {
        $query = "SELECT COUNT(pd.id) AS total 
        FROM 
            purchase_detail AS pd, forms AS ft, 
            admission_period AS ap, vendor_details AS v  
        WHERE
            pd.form_id = ft.id AND pd.admission_period = ap.id AND 
            pd.vendor = v.id AND ap.id = :ai AND v.vendor_name LIKE '%ONLINE%'";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    /**
     * Fetching form sales data by statistics
     */
    public function fetchFormsSoldStatsByVendor($admin_period)
    {
        $query = "SELECT 
                    v.vendor_name, COUNT(pd.id) AS total, SUM(ft.amount) AS amount 
                FROM 
                    purchase_detail AS pd, forms AS ft, 
                    admission_period AS ap, vendor_details AS v  
                WHERE
                    pd.form_id = ft.id AND pd.admission_period = ap.id AND 
                    pd.vendor = v.id AND ap.id = :ai 
                GROUP BY pd.vendor";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchFormsSoldStatsByPaymentMethod($admin_period)
    {
        $query = "SELECT 
                    pd.payment_method, COUNT(pd.id) AS total, SUM(ft.amount) AS amount 
                FROM 
                    purchase_detail AS pd, forms AS ft, 
                    admission_period AS ap, vendor_details AS v  
                WHERE
                    pd.form_id = ft.id AND pd.admission_period = ap.id AND 
                    pd.vendor = v.id AND ap.id = :ai 
                GROUP BY pd.payment_method";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchFormsSoldStatsByFormType($admin_period)
    {
        $query = "SELECT 
                    ft.name, COUNT(pd.id) AS total, SUM(ft.amount) AS amount 
                FROM 
                    purchase_detail AS pd, forms AS ft, 
                    admission_period AS ap, vendor_details AS v  
                WHERE
                    pd.form_id = ft.id AND pd.admission_period = ap.id AND 
                    pd.vendor = v.id AND ap.id = :ai 
                GROUP BY pd.form_id";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchFormsSoldStatsByCountry($admin_period)
    {
        $query = "SELECT 
                    pd.country_name, pd.country_code, COUNT(pd.id) AS total, SUM(ft.amount) AS amount 
                FROM 
                    purchase_detail AS pd, forms AS ft, 
                    admission_period AS ap, vendor_details AS v  
                WHERE
                    pd.form_id = ft.id AND pd.admission_period = ap.id AND 
                    pd.vendor = v.id AND ap.id = :ai 
                GROUP BY pd.country_code";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchFormsSoldStatsByPurchaseStatus($admin_period)
    {
        $query = "SELECT 
                    pd.status, COUNT(pd.id) AS total, SUM(ft.amount) AS amount 
                FROM 
                    purchase_detail AS pd, forms AS ft, 
                    admission_period AS ap, vendor_details AS v  
                WHERE
                    pd.form_id = ft.id AND pd.admission_period = ap.id AND 
                    pd.vendor = v.id AND ap.id = :ai 
                GROUP BY pd.status";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    /**
     * fetching applicants data
     */
    public function fetchAppsSummaryData($admin_period, $data)
    {
        // extract the array values into variables
        // create a new array with the keys of $data as the values and the values of $data as the keys
        // and then extract the values of the new array into variables
        extract(array_combine(array_keys($data), array_values($data)));

        $SQL_COND = "";
        if ($country != "All") $SQL_COND .= " AND p.`nationality` = '$country'";
        if ($type != "All") $SQL_COND .= " AND ft.`id` = $type";
        if ($program != "All") $SQL_COND .= " AND pi.`first_prog` = '$program' OR pi.`second_prog` = '$program'";

        $SQL_COND;

        $result = array();
        switch ($action) {
            case 'apps-total':
                $result = $this->fetchAllApplication($admin_period, $SQL_COND);
                break;
            case 'apps-submitted':
                $result = $this->fetchAllSubmittedApplication($admin_period, $SQL_COND);
                break;

            case 'apps-in-progress':
                $result = $this->fetchAllUnsubmittedApplication($admin_period, $SQL_COND);
                break;

            case 'apps-shortlisted':
                $result = $this->fetchAllShortlistedApplication($admin_period, $SQL_COND);
                break;

            case 'apps-admitted':
                $result = $this->fetchAllAdmittedApplication($admin_period, $SQL_COND);
                break;

            case 'apps-unadmitted':
                $result = $this->fetchAllUnAdmittedApplication($admin_period, $SQL_COND);
                break;

            case 'apps-awaiting':
                $result = $this->fetchAllAwaitingApplication($admin_period, $SQL_COND);
                break;

            case 'apps-enrolled':
                $result = $this->fetchAllEnrolledApplication($admin_period, $SQL_COND);
                break;
        }
        return $result;
    }

    public function fetchAllApplication($admin_period, $SQL_COND)
    {
        $query = "SELECT 
                    al.id, CONCAT(p.first_name, ' ', IFNULL(p.middle_name, ''), ' ', p.last_name) AS fullname, 
                    p.nationality, ft.name AS app_type, pi.first_prog, pi.second_prog, fs.declaration, fs.printed, 
                    p.phone_no1_code, p.phone_no1, pd.country_code, pd.phone_number 
                FROM 
                    personal_information AS p, applicants_login AS al, 
                    forms AS ft, purchase_detail AS pd, program_info AS pi, 
                    form_sections_chek AS fs, admission_period AS ap 
                WHERE 
                    p.app_login = al.id AND pi.app_login = al.id AND fs.app_login = al.id AND
                    pd.admission_period = ap.id AND pd.form_id = ft.id AND pd.id = al.purchase_id AND ap.id = :ai$SQL_COND";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchAllSubmittedApplication($admin_period, $SQL_COND)
    {
        $query = "SELECT 
                    al.id, CONCAT(p.first_name, ' ', IFNULL(p.middle_name, ''), ' ', p.last_name) AS fullname, 
                    p.nationality, ft.name AS app_type, pi.first_prog, pi.second_prog, fs.declaration, fs.printed, 
                    p.phone_no1_code, p.phone_no1, pd.country_code, pd.phone_number  
                FROM 
                    personal_information AS p, applicants_login AS al, 
                    forms AS ft, purchase_detail AS pd, program_info AS pi, 
                    form_sections_chek AS fs, admission_period AS ap 
                WHERE 
                    p.app_login = al.id AND pi.app_login = al.id AND fs.app_login = al.id AND 
                    pd.admission_period = ap.id AND pd.form_id = ft.id AND pd.id = al.purchase_id AND ap.id = :ai AND 
                    fs.declaration = 1 AND fs.admitted = 0 AND fs.enrolled = 0 AND fs.declined = 0$SQL_COND";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchAllUnsubmittedApplication($admin_period, $SQL_COND)
    {
        $query = "SELECT 
                    al.id, CONCAT(p.first_name, ' ', IFNULL(p.middle_name, ''), ' ', p.last_name) AS fullname, 
                    p.nationality, ft.name AS app_type, pi.first_prog, pi.second_prog, fs.declaration, fs.printed,
                    p.phone_no1_code, p.phone_no1, pd.country_code, pd.phone_number  
                FROM 
                    personal_information AS p, applicants_login AS al, 
                    forms AS ft, purchase_detail AS pd, program_info AS pi, 
                    form_sections_chek AS fs, admission_period AS ap 
                WHERE 
                    p.app_login = al.id AND pi.app_login = al.id AND fs.app_login = al.id AND
                    pd.admission_period = ap.id AND pd.form_id = ft.id AND pd.id = al.purchase_id AND ap.id = :ai AND 
                    fs.declaration = 0 AND fs.admitted = 0 AND fs.enrolled = 0 AND fs.declined = 0$SQL_COND";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchAllShortlistedApplication($admin_period, $SQL_COND)
    {
        $query = "SELECT 
                    al.id, CONCAT(p.first_name, ' ', IFNULL(p.middle_name, ''), ' ', p.last_name) AS fullname, 
                    p.nationality, ft.name AS app_type, pi.first_prog, pi.second_prog, fs.declaration, fs.printed,
                    p.phone_no1_code, p.phone_no1, pd.country_code, pd.phone_number  
                FROM 
                    personal_information AS p, applicants_login AS al, 
                    forms AS ft, purchase_detail AS pd, program_info AS pi, 
                    form_sections_chek AS fs, admission_period AS ap 
                WHERE 
                    p.app_login = al.id AND pi.app_login = al.id AND fs.app_login = al.id AND
                    pd.admission_period = ap.id AND pd.form_id = ft.id AND pd.id = al.purchase_id AND ap.id = :ai AND 
                    fs.declaration = 1 AND fs.shortlisted = 1 AND fs.admitted = 0 AND fs.enrolled = 0 AND fs.declined = 0$SQL_COND";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchAllAdmittedApplication($admin_period, $SQL_COND)
    {
        $query = "SELECT 
                    al.id, CONCAT(p.first_name, ' ', IFNULL(p.middle_name, ''), ' ', p.last_name) AS fullname, 
                    p.nationality, ft.name AS app_type, pi.first_prog, pi.second_prog, fs.declaration, fs.printed,
                    p.phone_no1_code, p.phone_no1, pd.country_code, pd.phone_number  
                FROM 
                    personal_information AS p, applicants_login AS al, 
                    forms AS ft, purchase_detail AS pd, program_info AS pi, 
                    form_sections_chek AS fs, admission_period AS ap 
                WHERE 
                    p.app_login = al.id AND pi.app_login = al.id AND fs.app_login = al.id AND
                    pd.admission_period = ap.id AND pd.form_id = ft.id AND pd.id = al.purchase_id AND ap.id = :ai AND 
                    fs.declaration = 1 AND fs.admitted = 1 AND fs.enrolled = 0 AND fs.declined = 0$SQL_COND";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchAllUnAdmittedApplication($admin_period, $SQL_COND)
    {
        $query = "SELECT 
                    al.id, CONCAT(p.first_name, ' ', IFNULL(p.middle_name, ''), ' ', p.last_name) AS fullname, 
                    p.nationality, ft.name AS app_type, pi.first_prog, pi.second_prog, fs.declaration, fs.printed,
                    p.phone_no1_code, p.phone_no1, pd.country_code, pd.phone_number  
                FROM 
                    personal_information AS p, applicants_login AS al, 
                    forms AS ft, purchase_detail AS pd, program_info AS pi, 
                    form_sections_chek AS fs, admission_period AS ap 
                WHERE 
                    p.app_login = al.id AND pi.app_login = al.id AND fs.app_login = al.id AND
                    pd.admission_period = ap.id AND pd.form_id = ft.id AND pd.id = al.purchase_id AND ap.id = :ai AND 
                    fs.declaration = 1 AND fs.admitted = 0 AND fs.enrolled = 0 AND fs.declined = 0$SQL_COND";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchAllAwaitingApplication($admin_period, $SQL_COND)
    {
        $query = "SELECT 
                    al.id, CONCAT(p.first_name, ' ', IFNULL(p.middle_name, ''), ' ', p.last_name) AS fullname, 
                    p.nationality, ft.name AS app_type, pi.first_prog, pi.second_prog, fs.declaration, fs.printed,
                    p.phone_no1_code, p.phone_no1, pd.country_code, pd.phone_number 
                FROM 
                    personal_information AS p, applicants_login AS al, 
                    forms AS ft, purchase_detail AS pd, program_info AS pi, 
                    form_sections_chek AS fs, admission_period AS ap, academic_background AS ab 
                WHERE 
                    p.app_login = al.id AND pi.app_login = al.id AND fs.app_login = al.id AND ab.app_login = al.id AND
                    pd.admission_period = ap.id AND pd.form_id = ft.id AND pd.id = al.purchase_id AND ap.id = :ai AND 
                    fs.declaration = 1 AND ab.awaiting_result = 1 AND ab.cert_type = 'WASSCE'$SQL_COND";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchAllEnrolledApplication($admin_period, $SQL_COND)
    {
        $query = "SELECT 
                    al.id, CONCAT(p.first_name, ' ', IFNULL(p.middle_name, ''), ' ', p.last_name) AS fullname, 
                    p.nationality, ft.name AS app_type, pi.first_prog, pi.second_prog, fs.declaration, fs.printed,
                    p.phone_no1_code, p.phone_no1, pd.country_code, pd.phone_number  
                FROM 
                    personal_information AS p, applicants_login AS al, 
                    forms AS ft, purchase_detail AS pd, program_info AS pi, 
                    form_sections_chek AS fs, admission_period AS ap 
                WHERE 
                    p.app_login = al.id AND pi.app_login = al.id AND fs.app_login = al.id AND
                    pd.admission_period = ap.id AND pd.form_id = ft.id AND pd.id = al.purchase_id AND ap.id = :ai AND 
                    fs.declaration = 1 AND fs.admitted = 1 AND fs.enrolled = 1 AND fs.declined = 0$SQL_COND";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    // fetch data by form type and admission period

    public function fetchTotalAppsByProgCodeAndAdmisPeriod($prog_code = "", $admin_period = 0): mixed
    {
        //$query = "SELECT COUNT(*) AS total FROM `forms` WHERE `form_category` <> :t";
        if ($admin_period == 0) {
            $query = "SELECT COUNT(*) AS total 
                FROM 
                    purchase_detail AS pd, admission_period AS ap, applicants_login AS al, forms AS ft, programs AS pg 
                WHERE 
                    ap.id = pd.admission_period AND ap.active = 1 AND al.purchase_id = pd.id 
                    AND pd.form_id = ft.id AND pg.type = ft.id AND pg.code = :pc";
            return $this->dm->getData($query, array(":pc" => $prog_code));
        } else {
            $query = "SELECT COUNT(*) AS total 
                FROM 
                    purchase_detail AS pd, admission_period AS ap, form_sections_chek AS fc, 
                    applicants_login AS al, forms AS ft, programs AS pg 
                WHERE 
                    ap.id = pd.admission_period AND fc.app_login = al.id AND al.purchase_id = pd.id 
                    AND pd.form_id = ft.id AND pg.type = ft.id AND pg.code = :pc AND ap.id = :a";
            return $this->dm->getData($query, array(":pc" => $prog_code, ":a" => $admin_period));
        }
    }

    public function fetchTotalApplicationsByFormTypeAndAdmPeriod(int $form_id = 0, $admin_period = 0)
    {
        if ($form_id == 0 && $admin_period == 0) {
            $query = "SELECT 
                    COUNT(*) AS total 
                FROM 
                    purchase_detail AS pd, admission_period AS ap, form_sections_chek AS fc, applicants_login AS al, forms AS ft 
                WHERE 
                    ap.id = pd.admission_period AND ap.active = 1 AND fc.app_login = al.id AND al.purchase_id = pd.id 
                    AND pd.form_id = ft.id";
            return $this->dm->getData($query);
        } else {
            $query = "SELECT 
                    COUNT(*) AS total 
                FROM 
                    purchase_detail AS pd, admission_period AS ap, form_sections_chek AS fc, applicants_login AS al, forms AS ft 
                WHERE 
                    ap.id = pd.admission_period AND fc.app_login = al.id AND al.purchase_id = pd.id 
                    AND pd.form_id = ft.id AND ft.id = :f AND ap.id = :a";
            return $this->dm->getData($query, array(":f" => $form_id, ":a" => $admin_period));
        }
    }

    public function fetchTotalApplicationsForMastersUpgraders($admin_period, string $prog_code)
    {
        if (empty($prog_code)) return 0;
        $SQL_COND = "";
        if ($prog_code == "UPGRADERS") $SQL_COND = " AND pg.code = 'UPGRADE'";
        else if ($prog_code == "MASTERS") $SQL_COND = " AND pg.code IN ('MSC', 'MA')";
        $query = "SELECT COUNT(DISTINCT pd.id) AS total 
                    FROM purchase_detail AS pd, admission_period AS ap, form_sections_chek AS fc, applicants_login AS al, forms AS ft, programs AS pg 
                    WHERE ap.id = pd.admission_period AND ap.id = :ai AND fc.app_login = al.id AND al.purchase_id = pd.id 
                        AND pd.form_id = ft.id AND ft.id = pg.type AND ft.id = 1 AND fc.declaration = 1 AND fc.admitted = 0$SQL_COND";
        return $this->dm->getData($query, array(":ai" => $admin_period));
    }

    public function fetchTotalApplications($admin_period, int $form_id = 100)
    {
        if ($form_id == 100) {
            $query = "SELECT COUNT(*) AS total 
                FROM purchase_detail AS pd, admission_period AS ap, form_sections_chek AS fc, applicants_login AS al, forms AS ft 
                WHERE ap.id = pd.admission_period AND ap.id = :ai AND fc.app_login = al.id AND al.purchase_id = pd.id 
                    AND pd.form_id = ft.id";
            return $this->dm->getData($query, array(":ai" => $admin_period));
        } else {
            $query = "SELECT COUNT(*) AS total 
                FROM purchase_detail AS pd, admission_period AS ap, form_sections_chek AS fc, applicants_login AS al, forms AS ft 
                WHERE ap.id = pd.admission_period AND ap.id = :ai AND fc.app_login = al.id AND al.purchase_id = pd.id 
                    AND pd.form_id = ft.id AND ft.id = :f";
            return $this->dm->getData($query, array(":f" => $form_id, ":ai" => $admin_period));
        }
    }

    public function fetchTotalSubmittedApps($admin_period, int $form_id)
    {
        $query = "SELECT COUNT(*) AS total 
                FROM purchase_detail AS pd, admission_period AS ap, form_sections_chek AS fc, applicants_login AS al, forms AS ft 
                WHERE ap.id = pd.admission_period AND ap.id = :ai AND fc.app_login = al.id AND al.purchase_id = pd.id 
                AND pd.form_id = ft.id AND fc.declaration = 1 AND fc.admitted = 0 AND fc.declined = 0 AND ft.id = :f";
        return $this->dm->getData($query, array(":f" => $form_id, ":ai" => $admin_period));
    }

    public function fetchTotalUnsubmittedApps($admin_period, int $form_id)
    {
        $query = "SELECT COUNT(*) AS total 
                FROM purchase_detail AS pd, admission_period AS ap, form_sections_chek AS fc, applicants_login AS al, forms AS ft 
                WHERE ap.id = pd.admission_period AND ap.id = :ai AND fc.app_login = al.id AND al.purchase_id = pd.id 
                AND pd.form_id = ft.id AND fc.declaration = 0 AND fc.admitted = 0  AND fc.declined = 0 AND ft.id = :f";
        return $this->dm->getData($query, array(":f" => $form_id, ":ai" => $admin_period));
    }

    public function fetchTotalShortlistedApplicants($admin_period, int $form_id)
    {
        $query = "SELECT COUNT(*) AS total 
                FROM purchase_detail AS pd, admission_period AS ap, form_sections_chek AS fc, applicants_login AS al, forms AS ft 
                WHERE ap.id = pd.admission_period AND ap.id = :ai AND fc.app_login = al.id AND al.purchase_id = pd.id 
                AND pd.form_id = ft.id AND fc.declaration = 1 AND fc.shortlisted = 1 AND fc.admitted = 0  AND fc.declined = 0 AND ft.id = :f";
        return $this->dm->getData($query, array(":f" => $form_id, ":ai" => $admin_period));
    }

    public function fetchTotalAdmittedApplicants($admin_period, int $form_id)
    {
        $query = "SELECT COUNT(*) AS total 
                FROM purchase_detail AS pd, admission_period AS ap, form_sections_chek AS fc, applicants_login AS al, forms AS ft 
                WHERE ap.id = pd.admission_period AND ap.id = :ai AND fc.app_login = al.id AND al.purchase_id = pd.id AND 
                pd.form_id = ft.id AND fc.declaration = 1 AND fc.admitted = 1 AND fc.enrolled = 0 AND fc.declined = 0 AND ft.id = :f";
        return $this->dm->getData($query, array(":f" => $form_id, ":ai" => $admin_period));
    }

    public function fetchTotalUnadmittedApplicants($admin_period, int $form_id)
    {
        $query = "SELECT COUNT(*) AS total 
                FROM purchase_detail AS pd, admission_period AS ap, form_sections_chek AS fc, applicants_login AS al, forms AS ft 
                WHERE ap.id = pd.admission_period AND ap.id = :ai AND fc.app_login = al.id AND al.purchase_id = pd.id AND 
                pd.form_id = ft.id AND fc.declaration = 1 AND fc.admitted = 0 AND fc.enrolled = 0 AND fc.declined = 0 AND ft.id = :f";
        return $this->dm->getData($query, array(":f" => $form_id, ":ai" => $admin_period));
    }

    public function fetchTotalEnrolledApplicants($admin_period, int $form_id)
    {
        $query = "SELECT COUNT(*) AS total 
                FROM purchase_detail AS pd, admission_period AS ap, form_sections_chek AS fc, applicants_login AS al, forms AS ft 
                WHERE ap.id = pd.admission_period AND ap.id = :ai AND fc.app_login = al.id AND al.purchase_id = pd.id AND 
                pd.form_id = ft.id AND fc.declaration = 1 AND fc.admitted = 1 AND fc.enrolled = 1 AND fc.declined = 0 AND ft.id = :f";
        return $this->dm->getData($query, array(":f" => $form_id, ":ai" => $admin_period));
    }

    public function fetchTotalAwaitingResultsByFormType($admin_period, int $form_id)
    {
        $query = "SELECT COUNT(*) AS total 
                FROM purchase_detail AS pd, admission_period AS ap, form_sections_chek AS fc, applicants_login AS al, forms AS ft, 
                academic_background AS ab 
                WHERE ap.id = pd.admission_period AND ap.id = :ai AND fc.app_login = al.id AND al.purchase_id = pd.id AND 
                ab.app_login = al.id AND pd.form_id = ft.id AND fc.`declaration` = 1 AND fc.admitted = 0 AND fc.declined = 0 
                AND ab.`awaiting_result` = 1 AND ft.id = :f AND ab.cert_type = 'WASSCE'";
        return $this->dm->getData($query, array(":f" => $form_id, ":ai" => $admin_period));
    }

    public function fetchTotalAwaitingResults()
    {
        $query = "SELECT COUNT(pd.id) AS total 
        FROM purchase_detail AS pd, form_sections_chek AS fc, 
        applicants_login AS al, forms AS ft, academic_background AS ab 
        WHERE fc.app_login = al.id AND 
        al.purchase_id = pd.id AND ab.app_login = al.id AND pd.form_id = ft.id AND fc.`declaration` = 1 AND 
        ab.`awaiting_result` = 1 AND ab.cert_type = 'WASSCE' AND ab.country = 'GHANA' AND 
        pd.id NOT IN (SELECT admission_number FROM downloaded_awaiting_results)";
        return $this->dm->getData($query);
    }

    public function getAllAdmittedApplicantsAllAll($cert_type)
    {
        $in_query = "";
        if ($cert_type != "All") $in_query = "AND ab.cert_type = '$cert_type'";
        $query = "SELECT p.`first_name`, p.`middle_name`, p.`last_name`, fs.application_term, pi.study_stream, a.id AS app_id 
                FROM `personal_information` AS p, `applicants_login` AS a, form_sections_chek AS fs 
                WHERE p.app_login = a.id AND fs.app_login = a.id AND fs.admitted = 1 $in_query";
        return $this->dm->getData($query);
    }

    public function getAllDeclinedApplicantsAllAll($cert_type)
    {
        $in_query = "";
        if ($cert_type != "All") $in_query = "AND ab.cert_type = '$cert_type'";
        $query = "SELECT p.`first_name`, p.`middle_name`, p.`last_name`, fs.first_prog_qualified, fs.second_prog_qualified, 
                    pi.first_prog, pi.second_prog, pi.application_term, pi.study_stream, pd.`form_id`, a.id  
                FROM `personal_information` AS p, `applicants_login` AS a, form_sections_chek AS fs, 
                    academic_background AS ab, program_info AS pi, purchase_detail AS pd 
                WHERE p.app_login = a.id AND ab.app_login = a.id AND fs.app_login = a.id AND 
                    pd.id = a.purchase_id AND pi.app_login = a.id AND fs.declined = 1 $in_query";
        return $this->dm->getData($query);
    }

    public function getAllAdmitedApplicants($cert_type)
    {
        $in_query = "";
        if (in_array($cert_type, ["WASSCE", "NECO"])) $in_query = "AND ab.cert_type IN ('WASSCE', 'NECO')";
        if (in_array($cert_type, ["SSSCE", "GBCE"])) $in_query = "AND ab.cert_type IN ('SSSCE', 'GBCE')";
        if (in_array($cert_type, ["BACCALAUREATE"])) $in_query = "AND ab.cert_type IN ('BACCALAUREATE')";
        if (in_array($cert_type, ["OTHERS"])) $in_query = "AND ab.cert_type NOT IN ('WASSCE', 'NECO', 'SSSCE', 'GBCE', 'BACCALAUREATE')";

        $query = "SELECT a.`id`, p.`first_name`, p.`middle_name`, p.`last_name`, pg.name AS programme, b.program_choice 
                FROM `personal_information` AS p, `applicants_login` AS a, broadsheets AS b, programs AS pg,  academic_background AS ab  
                WHERE p.app_login = a.id AND b.app_login = a.id AND ab.app_login = a.id AND pg.id = b.program_id AND 
                a.id IN (SELECT b.app_login AS id FROM broadsheets AS b) $in_query";
        return $this->dm->getData($query);
    }

    public function getAllUnadmitedApplicants($certificate, $progCategory, $admission_period)
    {
        $query = "SELECT DISTINCT l.`id`, p.`first_name`, p.`middle_name`, p.`last_name`, 
                    p.`email_addr`, i.`$progCategory` AS programme,  i.study_stream, a.`cert_type`, a.`other_cert_type`, a.`course_of_study`, a.`other_course_studied` 
                FROM 
                    `personal_information` AS p, `academic_background` AS a, `purchase_detail` AS pd, `admission_period` AS ap, 
                    `applicants_login` AS l, `form_sections_chek` AS f, `program_info` AS i, programs AS pg 
                WHERE 
                    pd.`id` = l.`purchase_id` AND pd.`admission_period` = ap.`id` AND 
                    p.`app_login` = l.`id` AND a.`app_login` = l.`id` AND f.`app_login` = l.`id` AND i.`app_login` = l.`id` AND
                    a.`awaiting_result` = 0 AND f.`declaration` = 1 AND f.`admitted` = 0 AND a.`cert_type` = :c AND ap.id = :a AND 
                    i.`$progCategory` = pg.name AND pg.category IN ('DEGREE', 'DIPLOMA')";
        return $this->dm->getData($query, array(":c" => $certificate, ":a" => $admission_period));
    }

    public function getAllUnadmitedShortApplicants($admission_period)
    {
        $query = "SELECT DISTINCT l.`id`, p.`first_name`, p.`middle_name`, p.`last_name`, 
                    p.`email_addr`, i.first_prog AS programme,  i.study_stream, a.`cert_type`, a.`other_cert_type`, a.`course_of_study`, a.`other_course_studied` 
                FROM 
                    `personal_information` AS p, `academic_background` AS a, `purchase_detail` AS pd, `admission_period` AS ap, 
                    `applicants_login` AS l, `form_sections_chek` AS f, `program_info` AS i, programs AS pg 
                WHERE 
                    pd.`id` = l.`purchase_id` AND pd.`admission_period` = ap.`id` AND 
                    p.`app_login` = l.`id` AND a.`app_login` = l.`id` AND f.`app_login` = l.`id` AND i.`app_login` = l.`id` AND
                    a.`awaiting_result` = 0 AND f.`declaration` = 1 AND f.`admitted` = 0 AND ap.id = :a AND 
                    i.first_prog = pg.name AND pg.category IN ('SHORT', 'VOCATIONAL', 'PROFESSIONAL')";
        return $this->dm->getData($query, array(':a' => $admission_period));
    }

    public function getAllSumittedApplicants($cert_type)
    {
        $in_query = "";
        if (in_array($cert_type, ["WASSCE", "NECO"])) $in_query = "AND ab.cert_type IN ('WASSCE', 'NECO')";
        if (in_array($cert_type, ["SSSCE", "GBCE"])) $in_query = "AND ab.cert_type IN ('SSSCE', 'GBCE')";
        if (in_array($cert_type, ["BACCALAUREATE"])) $in_query = "AND ab.cert_type IN ('BACCALAUREATE')";
        if (in_array($cert_type, ["OTHERS"])) $in_query = "AND ab.cert_type NOT IN ('WASSCE', 'NECO', 'SSSCE', 'GBCE', 'BACCALAUREATE')";

        $query = "SELECT a.`id`, p.`first_name`, p.`middle_name`, p.`last_name`, pg.name AS programme, b.program_choice 
                FROM `personal_information` AS p, `applicants_login` AS a, broadsheets AS b, programs AS pg,  academic_background AS ab  
                WHERE p.app_login = a.id AND b.app_login = a.id AND ab.app_login = a.id AND pg.id = b.program_id AND 
                a.id IN (SELECT b.app_login AS id FROM broadsheets AS b) $in_query";
        return $this->dm->getData($query);
    }

    public function getAppCourseSubjects(int $loginID)
    {
        $query = "SELECT 
                    r.`type`, r.`subject`, r.`grade` 
                FROM 
                    academic_background AS a, high_school_results AS r, applicants_login AS l
                WHERE 
                    l.`id` = a.`app_login` AND r.`acad_back_id` = a.`id` AND l.`id` = :i";
        return $this->dm->getData($query, array(":i" => $loginID));
    }

    /**
     * @param program mixed $program
     */
    public function getAppProgDetails($program)
    {
        $query = "SELECT `id`, `category`, `name`, `type`, `group`, `weekend` FROM programs WHERE `name` = :p";
        return $this->dm->getData($query, array(":p" => $program));
    }

    public function bundleApplicantsData($data, $prog_category = "")
    {
        $store = [];
        foreach ($data as  $appData) {
            if ($prog_category == "") $prog_category = $appData["program_choice"];
            $applicant["app_pers"] = $appData;
            $applicant["app_pers"]["prog_category"] = $prog_category;
            $subjs = $this->getAppCourseSubjects($appData["id"]);
            $applicant["sch_rslt"] = $subjs;
            $progs = $this->getAppProgDetails($appData["programme"]);
            $applicant["prog_info"] = !empty($progs) ? $progs[0] : [];
            array_push($store, $applicant);
        }
        return $store;
    }

    public function fetchAllUnadmittedApplicantsData($certificate, $progCategory, $admission_period = null)
    {
        $allAppData = $this->getAllUnadmitedApplicants($certificate, $progCategory, $admission_period);
        if (empty($allAppData)) return 0;
        $store = $this->bundleApplicantsData($allAppData, $progCategory);
        return $store;
    }

    public function fetchAllUnadmittedShortApplicantsData($admission_period = null)
    {
        $allAppData = $this->getAllUnadmitedShortApplicants($admission_period);
        if (empty($allAppData)) return 0;
        $store = $this->bundleApplicantsData($allAppData, 'first_prog');
        return $store;
    }

    public function fetchAllAdmittedApplicantsData1($cert_type)
    {
        $allAppData = $this->getAllAdmitedApplicants($cert_type);
        if (empty($allAppData)) return 0;
        $store = $this->bundleApplicantsData($allAppData);
        return $store;
    }

    public function fetchAllEnrolledApplicantsData($cert_type, $prog_type)
    {
        if (!empty($prog_type)) {
            $query = "SELECT s.*, 
            CONCAT(s.first_name, ' ', IF(s.middle_name <> '', CONCAT(s.middle_name, ' '), ''), s.last_name) AS full_name, 
            p.`id` AS program_id, p.`name` AS program_name, p.`category` AS program_category, p.`type` AS program_type  
            FROM `student` AS s, `programs` AS p WHERE s.`fk_program` = p.`id` AND p.`id` = :p";
            $params = array(":p" => $prog_type);
        } else if (!empty($cert_type)) {
            $query = "SELECT s.*, 
            CONCAT(s.first_name, ' ', IF(s.middle_name <> '', CONCAT(s.middle_name, ' '), ''), s.last_name) AS full_name, 
            p.`id` AS program_id, p.`name` AS program_name, p.`category` AS program_category, p.`type` AS program_type  
            FROM `student` AS s, `programs` AS p WHERE s.`fk_program` = p.`id` AND p.`category` = :c";
            $params = array(":c" => $cert_type);
        }
        return $this->dm->getData($query, $params);
    }

    public function fetchAllSubmittedApplicantsData($cert_type)
    {
        if ($cert_type == "MASTERS") $in_query = "WHERE pg.code IN ('MSC', 'MA')";
        else if ($cert_type == "UPGRADERS") $in_query = "WHERE pg.code = 'UPGRADE'";
        else if ($cert_type == "DEGREE") $in_query = "WHERE pg.code = 'BSC'";
        else if ($cert_type == "DIPLOMA") $in_query = "WHERE pg.code = 'DIPLOMA'";
        else if ($cert_type == "VOCATIONAL") $in_query = "WHERE pg.code = 'SHORT'";
        else return array("success" => false, "message" => "No match found for this certificate type [{$cert_type}]");

        $query = "SELECT 
                    a.`id`, CONCAT(p.first_name, ' ', IFNULL(p.middle_name, ''), ' ', p.last_name) AS full_name, 
                    YEAR(CURDATE()) - YEAR(p.`dob`) AS age, p.`nationality`, p.`gender` AS sex,
                    GROUP_CONCAT(
                        CONCAT(
                            CASE 
                                WHEN ab.`cert_type` = 'OTHER' THEN ab.`other_cert_type`
                                ELSE ab.`cert_type`
                            END,
                            ' - ',
                            ab.`school_name`,
                            ' (',
                            ab.`year_completed`,
                            ')'
                        ) 
                        ORDER BY ab.`year_completed` DESC
                    ) AS academic_background, pi.`first_prog` 
                FROM 
                    `applicants_login` AS a 
                    JOIN `personal_information` AS p ON a.`id` = p.`app_login` JOIN `form_sections_chek` AS fs ON a.`id` = fs.`app_login` 
                    JOIN `academic_background` AS ab ON a.`id` = ab.`app_login` JOIN `program_info` AS pi ON a.`id` = pi.`app_login` 
                WHERE fs.`declaration` = 1 AND pi.`first_prog` IN (SELECT pg.name FROM programs AS pg $in_query) 
                GROUP BY 
                    a.`id`, p.`first_name`, p.`middle_name`, p.`last_name`, age, p.`nationality`, p.`gender`, pi.`first_prog`;
                ";
        $result = $this->dm->getData($query);
        if (empty($result)) return array("success" => false, "message" => "No result found!");
        return array("success" => true, "message" => $result);
    }

    public function saveAdmittedApplicantData(int $admin_period, int $appID, int $program_id, $admitted_data, $prog_choice)
    {
        if (empty($appID) || empty($admin_period) || empty($program_id) || empty($admitted_data)) return 0;

        $query = "INSERT INTO `broadsheets` (`admin_period`, `app_login`, `program_id`, 
        `required_core_passed`, `required_core_subjects`, `total_core_score`, 
        `required_elective_passed`, `required_elective_subjects`, `additional_elective_subjects`, 
        `total_elective_score`, `total_score`, `program_choice`) 
                VALUES (:ap, :al, :pi, :rcp, :rcs, :rep, :res, :ates, :tcs, :tes, :ts, :pc)";
        $params = array(
            ":ap" => $admin_period,
            ":al" => $appID,
            ":pi" => $program_id,
            ":rcp" => $admitted_data["required_core_passed"],
            ":rcs" => $admitted_data["required_core_subjects"],
            ":tcs" => $admitted_data["total_core_score"],
            ":rep" => $admitted_data["required_elective_passed"],
            ":res" => $admitted_data["required_elective_subjects"],
            ":ates" => $admitted_data["additional_elective_subjects"],
            ":tes" => $admitted_data["total_elective_score"],
            ":ts" => $admitted_data["total_score"],
            ":pc" => $prog_choice
        );
        return $this->dm->inputData($query, $params);
    }

    /*
    * Admit applicants in groups by their certificate category
    */

    public function admitQualifiedShortApps($admission_period = null)
    {
        $students_bs_data = $this->fetchAllUnadmittedShortApplicantsData($admission_period);
        if (!empty($students_bs_data)) {
            return $this->processShortApplicants($students_bs_data);
        }
        return 0;
    }

    public function shortlistQualifiedStudents($certificate, $progCategory, $admission_period = null)
    {
        $students_bs_data = $this->fetchAllUnadmittedApplicantsData($certificate, $progCategory, $admission_period);
        if (!empty($students_bs_data)) {
            $qualifications = array(
                "A" => array('WASSCE', 'SSSCE', 'GBCE', 'NECO'),
                "B" => array('GCE', "GCE 'A' Level", "GCE 'O' Level"),
                "C" => array('HND'),
                "D" => array('IB', 'International Baccalaureate', 'Baccalaureate'),
            );
            return $this->processApplicants($students_bs_data, $qualifications);
        }
        return 0;
    }

    public function admitShortApplicants($app_id, $prog_id, $stream, $extras = []): mixed
    {
        $extras_query = "";
        if (!empty($extras)) {
            $extras_query = "`emailed_letter` = 1, `notified_sms` = 1, ";
        } else {
            if (isset($extras["emailed_letter"])) {
                if (empty($extras["emailed_letter"])) $extras_query .= "`emailed_letter` = 0, ";
                else $extras_query .= "`emailed_letter` = 1, ";
            }
            if (isset($extras["notified_sms"])) {
                if (empty($extras["notified_sms"])) $extras_query .= "`notified_sms` = 0, ";
                else $extras_query .= "`notified_sms` = 1, ";
            }
        }
        $query = "UPDATE `form_sections_chek` 
        SET shortlisted = 1, `admitted` = 1, `declined` = 0,{$extras_query} `programme_awarded` = :p, `stream_admitted` = :s 
        WHERE `app_login` = :i";
        return ($this->dm->inputData($query, array(":i" => $app_id, ":p" => $prog_id, ":s" => $stream)));
    }

    public function processShortApplicants(array $applicantData)
    {
        $admitted = 0;
        foreach ($applicantData as $applicant) {
            $status = $this->admitShortApplicants($applicant['app_pers']['id'], $applicant['prog_info']['id'], $applicant['app_pers']['study_stream'], 100, true, true);
            if ($status) {
                $admitted++;
            }
        }
        return array("success" => true, "message" => "successfully admitted {$admitted} applicants in vocational/professional courses");
    }

    public function processApplicants(array $applicantData, array $qualifications)
    {
        $admitted_list = $failed_list = [];
        $total_admitted = $total_failed = 0;

        foreach ($applicantData as $applicant) {
            if (!empty($applicant['app_pers']) && !empty($applicant['prog_info']) && !empty($applicant['sch_rslt'])) {
                $category = $this->getApplicantCategory($applicant['app_pers']['cert_type'], $qualifications);
                switch ($category) {
                    case 'WASSCE':
                        $admissionState = $this->shortlistWASSCEApplicant($applicant);
                        if ($admissionState['status'] && isset($admissionState['status']['admitted']) && $admissionState['status']['admitted']) {
                            $total_admitted += 1;
                            $admitted_list[] = $admissionState;
                        } else {
                            $total_failed += 1;
                            $failed_list[] = $admissionState;
                        }
                        break;

                    case 'SSSCE':
                        $admissionState = $this->shortlistSSSCEApplicant($applicant);
                        if ($admissionState['status'] && isset($admissionState['status']['admitted']) && $admissionState['status']['admitted']) {
                            $total_admitted += 1;
                            $admitted_list[] = $admissionState;
                        } else {
                            $total_failed += 1;
                            $failed_list[] = $admissionState;
                        }
                        break;

                    default:
                        # code...
                        break;
                }
            }
        }

        // Save the admitted applicants
        // if (!empty($admitted_list)) {
        //     foreach ($admitted_list as $admitted) {
        //         $this->saveAdmittedApplicantData(
        //             $this->getCurrentAdmissionPeriodId(),
        //             $admitted['app_id'],
        //             $admitted['prog_id'],
        //             $admitted['feed'],
        //             $admitted['prog_category']
        //         );
        //     }
        // }

        $result = [
            'success' => true,
            'admitted' => [
                'list' => $admitted_list,
                'count' => $total_admitted
            ],
            'failed' => [
                'list' => $failed_list,
                'count' => $total_failed
            ]
        ];

        return $result;
    }

    private function getApplicantCategory(string $cert_type, array $qualifications): string
    {
        foreach ($qualifications as $category => $qualifications) {
            if (in_array($cert_type, $qualifications)) {
                return $cert_type;
            }
        }
        return 'Unknown';
    }

    private function shortlistWASSCEApplicant(array $applicant): array
    {
        $gradeRange = [
            ['grade' => 'A1', 'score' => 1],
            ['grade' => 'B2', 'score' => 2],
            ['grade' => 'B3', 'score' => 3],
            ['grade' => 'C4', 'score' => 4],
            ['grade' => 'C5', 'score' => 5],
            ['grade' => 'C6', 'score' => 6],
            ['grade' => 'D7', 'score' => 7],
            ['grade' => 'E8', 'score' => 8],
            ['grade' => 'F9', 'score' => 9]
        ];

        // Create evaluator instance
        //$evaluator = new ApplicantEvaluator($gradeRange);
        $program_category = strtolower($applicant['prog_info']['category']);

        $result = [
            'app_id' => $applicant['app_pers']['id'],
            'prog_id' => $applicant['prog_info']['id'],
            "feed" => [],
            'prog_category' => $applicant['app_pers']['prog_category'],
            'mode' => 'WASSCE',
            "status" => null
        ];

        if (in_array($program_category, ['degree', 'diploma'])) {
            //$result['feed'] = $evaluator->evaluateApplicant($applicant, $program_category);
            $result['feed'] = $this->evaluateApplicant($applicant, $gradeRange);
            $result['status'] = $this->checkAndShortlistApplicant($result, $applicant['app_pers']['study_stream']);
        }

        return $result;
    }

    private function shortlistSSSCEApplicant(array $applicant): array
    {
        $gradeRange = [
            ['grade' => 'A', 'score' => 1],
            ['grade' => 'B', 'score' => 2],
            ['grade' => 'C', 'score' => 3],
            ['grade' => 'D', 'score' => 4],
            ['grade' => 'E', 'score' => 5],
            ['grade' => 'F', 'score' => 6]
        ];

        // Create evaluator instance
        //$evaluator = new ApplicantEvaluator($gradeRange);
        $program_category = strtolower($applicant['prog_info']['category']);

        $result = [
            'app_id' => $applicant['app_pers']['id'],
            'prog_id' => $applicant['prog_info']['id'],
            "feed" => [],
            'prog_category' => $applicant['app_pers']['prog_category'],
            'mode' => 'SSSCE',
            "status" => null
        ];

        if (in_array($program_category, ['degree', 'diploma'])) {
            //$result['feed'] = $evaluator->evaluateApplicant($applicant, $program_category);
            $result['feed'] = $this->evaluateApplicant($applicant, $gradeRange);
            $result['status'] = $this->checkAndShortlistApplicant($result, $applicant['app_pers']['study_stream']);
        }

        return $result;
    }

    private function evaluateApplicant($applicant, $gradeRange)
    {
        $requiredCorePassed = 0;
        $anyCorePassed = 0;
        $requiredElectivePassed = 0;
        $anyElectivePassed = 0;

        $requiredCoreSubjects = [];
        $anyCoreSubjects = [];
        $requiredElectiveSubjects = [];
        $anyElectiveSubjects = [];

        $program_group = $applicant['prog_info']['group'];
        $program_name = strtolower($applicant['prog_info']['name']);
        $program_category = strtolower($applicant['prog_info']['category']);
        $program_type = 0;
        $course_of_study = strtolower($applicant['app_pers']['course_of_study']);

        switch ($program_category) {
            case 'degree':
                if ($program_group == 'A') {
                    if ($course_of_study == 'general science' || $course_of_study == 'science') {
                        $program_type = 1;
                        foreach ($applicant['sch_rslt'] as $result) {
                            $score = 0;
                            $subject_type = strtolower($result['type']);
                            $subject = strtolower($result['subject']);

                            foreach ($gradeRange as $range) {
                                if ($result['grade'] == $range['grade']) {
                                    $score = $range['score'];
                                    break;
                                }
                            }

                            if ($score >= 1 && $score <= 6) {
                                if ($subject_type == 'core') {
                                    if ($subject == 'core mathematics' || $subject == 'core maths') {
                                        if (array_key_exists('core mathematics', $requiredCoreSubjects)) {
                                            if ($score < $requiredCoreSubjects['core mathematics']) {
                                                $requiredCoreSubjects['core mathematics'] = $score;
                                            }
                                        } else {
                                            $requiredCoreSubjects['core mathematics'] = $score;
                                            $requiredCorePassed++;
                                        }
                                    } elseif ($subject == 'english language' || $subject == 'english lang' || $subject == 'english') {
                                        if (array_key_exists('english language', $requiredCoreSubjects)) {
                                            if ($score < $requiredCoreSubjects['english language']) {
                                                $requiredCoreSubjects['english language'] = $score;
                                            }
                                        } else {
                                            $requiredCoreSubjects['english language'] = $score;
                                            $requiredCorePassed++;
                                        }
                                    } elseif ($subject == 'integrated science' || $subject == 'int science') {
                                        if (array_key_exists('integrated science', $requiredCoreSubjects)) {
                                            if ($score < $requiredCoreSubjects['integrated science']) {
                                                $requiredCoreSubjects['integrated science'] = $score;
                                            }
                                        } else {
                                            $requiredCoreSubjects['integrated science'] = $score;
                                            $requiredCorePassed++;
                                        }
                                    } else {
                                        if (array_key_exists($subject, $anyCoreSubjects)) {
                                            if ($score < $anyCoreSubjects[$subject]) {
                                                $anyCoreSubjects[$subject] = $score;
                                            }
                                        } else {
                                            $anyCoreSubjects[$subject] = $score;
                                            $anyCorePassed++;
                                        }
                                    }
                                }
                                if ($subject_type == 'elective') {
                                    if ($subject == 'elective mathematics' || $subject == 'elective maths') {
                                        if (array_key_exists('elective mathematics', $anyElectiveSubjects)) {
                                            if ($score < $anyElectiveSubjects['elective mathematics']) {
                                                $anyElectiveSubjects['elective mathematics'] = $score;
                                            }
                                        } else {
                                            $requiredElectiveSubjects['elective mathematics'] = $score;
                                            $requiredElectivePassed++;
                                        }
                                    } else {
                                        if (array_key_exists($subject, $anyElectiveSubjects)) {
                                            if ($score < $anyElectiveSubjects[$subject]) {
                                                $anyElectiveSubjects[$subject] = $score;
                                            }
                                        } else {
                                            $anyElectiveSubjects[$subject] = $score;
                                            $anyElectiveScores[] = $score;
                                            $anyElectivePassed++;
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        $program_type = 2;
                        foreach ($applicant['sch_rslt'] as $result) {
                            $score = 0;
                            $subject_type = strtolower($result['type']);
                            $subject = strtolower($result['subject']);

                            foreach ($gradeRange as $range) {
                                if ($result['grade'] == $range['grade']) {
                                    $score = $range['score'];
                                    break;
                                }
                            }

                            if ($score >= 1 && $score <= 6) {
                                if ($subject_type == 'core') {
                                    if ($subject == 'core mathematics' || $subject == 'core maths') {
                                        if (array_key_exists('core mathematics', $requiredCoreSubjects)) {
                                            if ($score < $requiredCoreSubjects['core mathematics']) {
                                                $requiredCoreSubjects['core mathematics'] = $score;
                                            }
                                        } else {
                                            $requiredCoreSubjects['core mathematics'] = $score;
                                            $requiredCorePassed++;
                                        }
                                    } elseif ($subject == 'english language' || $subject == 'english lang' || $subject == 'english') {
                                        if (array_key_exists('english language', $requiredCoreSubjects)) {
                                            if ($score < $requiredCoreSubjects['english language']) {
                                                $requiredCoreSubjects['english language'] = $score;
                                            }
                                        } else {
                                            $requiredCoreSubjects['english language'] = $score;
                                            $requiredCorePassed++;
                                        }
                                    } elseif ($subject == 'integrated science' || $subject == 'int science') {
                                        if (array_key_exists('integrated science', $requiredCoreSubjects)) {
                                            if ($score < $requiredCoreSubjects['integrated science']) {
                                                $requiredCoreSubjects['integrated science'] = $score;
                                            }
                                        } else {
                                            $requiredCoreSubjects['integrated science'] = $score;
                                            $requiredCorePassed++;
                                        }
                                    } else {
                                        if (array_key_exists($subject, $anyCoreSubjects)) {
                                            if ($score < $anyCoreSubjects[$subject]) {
                                                $anyCoreSubjects[$subject] = $score;
                                            }
                                        } else {
                                            $anyCoreSubjects[$subject] = $score;
                                            $anyCorePassed++;
                                        }
                                    }
                                }

                                if ($subject_type == 'elective') {
                                    if ($program_name == 'b.sc. nautical science') {
                                        if ($subject == 'elective mathematics' || $subject == 'elective maths') {
                                            if (array_key_exists('elective mathematics', $requiredElectiveSubjects)) {
                                                if ($score < $requiredElectiveSubjects['elective mathematics']) {
                                                    $requiredElectiveSubjects['elective mathematics'] = $score;
                                                }
                                            } else {
                                                $requiredElectiveSubjects['elective mathematics'] = $score;
                                                $requiredElectivePassed++;
                                            }
                                        } else if ($subject == 'geography') {
                                            if (array_key_exists('geography', $requiredElectiveSubjects)) {
                                                if ($score < $requiredElectiveSubjects['geography']) {
                                                    $requiredElectiveSubjects['geography'] = $score;
                                                }
                                            } else {
                                                $requiredElectiveSubjects['geography'] = $score;
                                                $requiredElectivePassed++;
                                            }
                                        } else {
                                            if (array_key_exists($subject, $anyElectiveSubjects)) {
                                                if ($score < $anyElectiveSubjects[$subject]) {
                                                    $anyElectiveSubjects[$subject] = $score;
                                                }
                                            } else {
                                                $anyElectiveSubjects[$subject] = $score;
                                                $anyElectiveScores[] = $score;
                                                $anyElectivePassed++;
                                            }
                                        }
                                    } else {
                                        if ($subject == 'elective mathematics' || $subject == 'elective maths') {
                                            if (array_key_exists('elective mathematics', $anyElectiveSubjects)) {
                                                if ($score < $anyElectiveSubjects['elective mathematics']) {
                                                    $anyElectiveSubjects['elective mathematics'] = $score;
                                                }
                                            } else {
                                                $requiredElectiveSubjects['elective mathematics'] = $score;
                                                $requiredElectivePassed++;
                                            }
                                        } else {
                                            if (array_key_exists($subject, $anyElectiveSubjects)) {
                                                if ($score < $anyElectiveSubjects[$subject]) {
                                                    $anyElectiveSubjects[$subject] = $score;
                                                }
                                            } else {
                                                $anyElectiveSubjects[$subject] = $score;
                                                $anyElectiveScores[] = $score;
                                                $anyElectivePassed++;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                } else if ($program_group == 'B') {
                    foreach ($applicant['sch_rslt'] as $result) {
                        $score = 0;
                        $subject_type = strtolower($result['type']);
                        $subject = strtolower($result['subject']);

                        foreach ($gradeRange as $range) {
                            if ($result['grade'] == $range['grade']) {
                                $score = $range['score'];
                                break;
                            }
                        }

                        if ($score >= 1 && $score <= 6) {
                            if ($subject_type == 'core') {
                                if ($subject == 'core mathematics' || $subject == 'core maths') {
                                    if (array_key_exists('core mathematics', $requiredCoreSubjects)) {
                                        if ($score < $requiredCoreSubjects['core mathematics']) {
                                            $requiredCoreSubjects['core mathematics'] = $score;
                                        }
                                    } else {
                                        $requiredCoreSubjects['core mathematics'] = $score;
                                        $requiredCorePassed++;
                                    }
                                } elseif ($subject == 'english language' || $subject == 'english lang' || $subject == 'english') {
                                    if (array_key_exists('english language', $requiredCoreSubjects)) {
                                        if ($score < $requiredCoreSubjects['english language']) {
                                            $requiredCoreSubjects['english language'] = $score;
                                        }
                                    } else {
                                        $requiredCoreSubjects['english language'] = $score;
                                        $requiredCorePassed++;
                                    }
                                } elseif ($subject == 'integrated science' || $subject == 'int science') {
                                    if (array_key_exists('integrated science', $requiredCoreSubjects)) {
                                        if ($score < $requiredCoreSubjects['integrated science']) {
                                            $requiredCoreSubjects['integrated science'] = $score;
                                        }
                                    } else {
                                        $requiredCoreSubjects['integrated science'] = $score;
                                        $requiredCorePassed++;
                                    }
                                } else {
                                    if (array_key_exists($subject, $anyCoreSubjects)) {
                                        if ($score < $anyCoreSubjects[$subject]) {
                                            $anyCoreSubjects[$subject] = $score;
                                        }
                                    } else {
                                        $anyCoreSubjects[$subject] = $score;
                                        $anyCorePassed++;
                                    }
                                }
                            }
                            if (strtolower($result['type']) == 'elective') {
                                if (array_key_exists($subject, $anyElectiveSubjects)) {
                                    if ($score < $anyElectiveSubjects[$subject]) {
                                        $anyElectiveSubjects[$subject] = $score;
                                    }
                                } else {
                                    $anyElectiveSubjects[$subject] = $score;
                                    $anyElectiveScores[] = $score;
                                    $anyElectivePassed++;
                                }
                            }
                        }
                    }
                }
                break;

            case 'diploma':
                if ($program_group == 'A') {
                    if ($course_of_study == 'general science' || $course_of_study == 'science') {
                        $program_type = 1;
                        foreach ($applicant['sch_rslt'] as $result) {
                            $score = 0;
                            $subject_type = strtolower($result['type']);
                            $subject = strtolower($result['subject']);

                            foreach ($gradeRange as $range) {
                                if ($result['grade'] == $range['grade']) {
                                    $score = $range['score'];
                                    break;
                                }
                            }

                            if ($score >= 1 && $score <= 7) {
                                if ($subject_type == 'core') {
                                    if ($subject == 'core mathematics' || $subject == 'core maths') {
                                        if (array_key_exists('core mathematics', $requiredCoreSubjects)) {
                                            if ($score < $requiredCoreSubjects['core mathematics']) {
                                                $requiredCoreSubjects['core mathematics'] = $score;
                                            }
                                        } else {
                                            $requiredCoreSubjects['core mathematics'] = $score;
                                            $requiredCorePassed++;
                                        }
                                    } elseif ($subject == 'english language' || $subject == 'english lang' || $subject == 'english') {
                                        if (array_key_exists('english language', $requiredCoreSubjects)) {
                                            if ($score < $requiredCoreSubjects['english language']) {
                                                $requiredCoreSubjects['english language'] = $score;
                                            }
                                        } else {
                                            $requiredCoreSubjects['english language'] = $score;
                                            $requiredCorePassed++;
                                        }
                                    } elseif ($subject == 'integrated science' || $subject == 'int science') {
                                        if (array_key_exists('integrated science', $requiredCoreSubjects)) {
                                            if ($score < $requiredCoreSubjects['integrated science']) {
                                                $requiredCoreSubjects['integrated science'] = $score;
                                            }
                                        } else {
                                            $requiredCoreSubjects['integrated science'] = $score;
                                            $requiredCorePassed++;
                                        }
                                    } else {
                                        if (array_key_exists($subject, $anyCoreSubjects)) {
                                            if ($score < $anyCoreSubjects[$subject]) {
                                                $anyCoreSubjects[$subject] = $score;
                                            }
                                        } else {
                                            $anyCoreSubjects[$subject] = $score;
                                            $anyCorePassed++;
                                        }
                                    }
                                }
                                if (strtolower($result['type']) == 'elective') {
                                    if ($subject == 'elective mathematics' || $subject == 'elective maths') {
                                        if (array_key_exists('elective mathematics', $anyElectiveSubjects)) {
                                            if ($score < $anyElectiveSubjects['elective mathematics']) {
                                                $anyElectiveSubjects['elective mathematics'] = $score;
                                            }
                                        } else {
                                            $requiredElectiveSubjects['elective mathematics'] = $score;
                                            $requiredElectivePassed++;
                                        }
                                    } else {
                                        if (array_key_exists($subject, $anyElectiveSubjects)) {
                                            if ($score < $anyElectiveSubjects[$subject]) {
                                                $anyElectiveSubjects[$subject] = $score;
                                            }
                                        } else {
                                            $anyElectiveSubjects[$subject] = $score;
                                            $anyElectiveScores[] = $score;
                                            $anyElectivePassed++;
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        $program_type = 2;
                        foreach ($applicant['sch_rslt'] as $result) {
                            $score = 0;
                            $subject_type = strtolower($result['type']);
                            $subject = strtolower($result['subject']);

                            foreach ($gradeRange as $range) {
                                if ($result['grade'] == $range['grade']) {
                                    $score = $range['score'];
                                    break;
                                }
                            }

                            if ($score >= 1 && $score <= 7) {
                                if ($subject_type == 'core') {
                                    if ($subject == 'core mathematics' || $subject == 'core maths') {
                                        if (array_key_exists('core mathematics', $requiredCoreSubjects)) {
                                            if ($score < $requiredCoreSubjects['core mathematics']) {
                                                $requiredCoreSubjects['core mathematics'] = $score;
                                            }
                                        } else {
                                            $requiredCoreSubjects['core mathematics'] = $score;
                                            $requiredCorePassed++;
                                        }
                                    } elseif ($subject == 'english language' || $subject == 'english lang' || $subject == 'english') {
                                        if (array_key_exists('english language', $requiredCoreSubjects)) {
                                            if ($score < $requiredCoreSubjects['english language']) {
                                                $requiredCoreSubjects['english language'] = $score;
                                            }
                                        } else {
                                            $requiredCoreSubjects['english language'] = $score;
                                            $requiredCorePassed++;
                                        }
                                    } elseif ($subject == 'integrated science' || $subject == 'int science') {
                                        if (array_key_exists('integrated science', $requiredCoreSubjects)) {
                                            if ($score < $requiredCoreSubjects['integrated science']) {
                                                $requiredCoreSubjects['integrated science'] = $score;
                                            }
                                        } else {
                                            $requiredCoreSubjects['integrated science'] = $score;
                                            $requiredCorePassed++;
                                        }
                                    } else {
                                        if (array_key_exists($subject, $anyCoreSubjects)) {
                                            if ($score < $anyCoreSubjects[$subject]) {
                                                $anyCoreSubjects[$subject] = $score;
                                            }
                                        } else {
                                            $anyCoreSubjects[$subject] = $score;
                                            $anyCorePassed++;
                                        }
                                    }
                                }
                                if (strtolower($result['type']) == 'elective') {
                                    if ($program_name == 'b.sc. nautical science') {
                                        if ($subject == 'elective mathematics' || $subject == 'elective maths') {
                                            if (array_key_exists('elective mathematics', $requiredElectiveSubjects)) {
                                                if ($score < $requiredElectiveSubjects['elective mathematics']) {
                                                    $requiredElectiveSubjects['elective mathematics'] = $score;
                                                }
                                            } else {
                                                $requiredElectiveSubjects['elective mathematics'] = $score;
                                                $requiredElectivePassed++;
                                            }
                                        } else if ($subject == 'geography') {
                                            if (array_key_exists('geography', $requiredElectiveSubjects)) {
                                                if ($score < $requiredElectiveSubjects['geography']) {
                                                    $requiredElectiveSubjects['geography'] = $score;
                                                }
                                            } else {
                                                $requiredElectiveSubjects['geography'] = $score;
                                                $requiredElectivePassed++;
                                            }
                                        } else {
                                            if (array_key_exists($subject, $anyElectiveSubjects)) {
                                                if ($score < $anyElectiveSubjects[$subject]) {
                                                    $anyElectiveSubjects[$subject] = $score;
                                                }
                                            } else {
                                                $anyElectiveSubjects[$subject] = $score;
                                                $anyElectiveScores[] = $score;
                                                $anyElectivePassed++;
                                            }
                                        }
                                    } else {
                                        if ($subject == 'elective mathematics' || $subject == 'elective maths') {
                                            if (array_key_exists('elective mathematics', $anyElectiveSubjects)) {
                                                if ($score < $anyElectiveSubjects['elective mathematics']) {
                                                    $anyElectiveSubjects['elective mathematics'] = $score;
                                                }
                                            } else {
                                                $requiredElectiveSubjects['elective mathematics'] = $score;
                                                $requiredElectivePassed++;
                                            }
                                        } else {
                                            if (array_key_exists($subject, $anyElectiveSubjects)) {
                                                if ($score < $anyElectiveSubjects[$subject]) {
                                                    $anyElectiveSubjects[$subject] = $score;
                                                }
                                            } else {
                                                $anyElectiveSubjects[$subject] = $score;
                                                $anyElectiveScores[] = $score;
                                                $anyElectivePassed++;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                } else if ($program_group == 'B') {
                    foreach ($applicant['sch_rslt'] as $result) {
                        $score = 0;
                        $subject_type = strtolower($result['type']);
                        $subject = strtolower($result['subject']);

                        foreach ($gradeRange as $range) {
                            if ($result['grade'] == $range['grade']) {
                                $score = $range['score'];
                                break;
                            }
                        }

                        if ($score >= 1 && $score <= 7) {
                            if ($subject_type == 'core') {
                                if ($subject == 'core mathematics' || $subject == 'core maths') {
                                    if (array_key_exists('core mathematics', $requiredCoreSubjects)) {
                                        if ($score < $requiredCoreSubjects['core mathematics']) {
                                            $requiredCoreSubjects['core mathematics'] = $score;
                                        }
                                    } else {
                                        $requiredCoreSubjects['core mathematics'] = $score;
                                        $requiredCorePassed++;
                                    }
                                } elseif ($subject == 'english language' || $subject == 'english lang' || $subject == 'english') {
                                    if (array_key_exists('english language', $requiredCoreSubjects)) {
                                        if ($score < $requiredCoreSubjects['english language']) {
                                            $requiredCoreSubjects['english language'] = $score;
                                        }
                                    } else {
                                        $requiredCoreSubjects['english language'] = $score;
                                        $requiredCorePassed++;
                                    }
                                } elseif ($subject == 'integrated science' || $subject == 'int science') {
                                    if (array_key_exists('integrated science', $requiredCoreSubjects)) {
                                        if ($score < $requiredCoreSubjects['integrated science']) {
                                            $requiredCoreSubjects['integrated science'] = $score;
                                        }
                                    } else {
                                        $requiredCoreSubjects['integrated science'] = $score;
                                        $requiredCorePassed++;
                                    }
                                } else {
                                    if (array_key_exists($subject, $anyCoreSubjects)) {
                                        if ($score < $anyCoreSubjects[$subject]) {
                                            $anyCoreSubjects[$subject] = $score;
                                        }
                                    } else {
                                        $anyCoreSubjects[$subject] = $score;
                                        $anyCorePassed++;
                                    }
                                }
                            }
                            if (strtolower($result['type']) == 'elective') {
                                if (array_key_exists($subject, $anyElectiveSubjects)) {
                                    if ($score < $anyElectiveSubjects[$subject]) {
                                        $anyElectiveSubjects[$subject] = $score;
                                    }
                                } else {
                                    $anyElectiveSubjects[$subject] = $score;
                                    $anyElectiveScores[] = $score;
                                    $anyElectivePassed++;
                                }
                            }
                        }
                    }
                }
                break;
        }

        asort($anyElectiveSubjects);
        asort($requiredCoreSubjects);
        asort($requiredElectiveSubjects);

        $res_count = 1;
        $aes_count = 2;

        if ($program_group == 'A') {
            if ($course_of_study == 'general science' || $course_of_study == 'science') {
                $res_count = 1;
                $aes_count = 2;
            } else {
                $res_count = 2;
                $aes_count = 1;
            }
        } else if ($program_group == 'B') {
            $res_count = 0;
            $aes_count = 3;
        }

        $sortedRequiredCoreSubjects = array_slice($requiredCoreSubjects, 0, 3);
        $sortedRequiredElectiveSubjects = array_slice($requiredElectiveSubjects, 0, $res_count);
        $sortedAnyElectiveSubjects = array_slice($anyElectiveSubjects, 0, $aes_count);

        $totalCoreScore = array_sum($sortedRequiredCoreSubjects);
        $requiredElectiveScore = array_sum($sortedRequiredElectiveSubjects);
        $anyBestElectiveScore = array_sum($sortedAnyElectiveSubjects);

        $totalElectiveScore = $anyBestElectiveScore + $requiredElectiveScore;
        $totalScore = $totalCoreScore + $totalElectiveScore;

        if ($program_group == 'B') {
            $requiredElectivePassed = count($sortedAnyElectiveSubjects);
        }

        return [
            'program_group' => $program_group,
            'program_category' => $program_category,
            'program_type' => $program_type,
            'required_core_passed' => $requiredCorePassed,
            'required_elective_passed' => $requiredElectivePassed,
            'required_core_subjects' => $sortedRequiredCoreSubjects,
            'required_elective_subjects' => $sortedRequiredElectiveSubjects,
            'additional_elective_subjects' => $sortedAnyElectiveSubjects,
            'total_core_score' => $totalCoreScore,
            'total_elective_score' => $totalElectiveScore,
            'total_score' => $totalScore
        ];
    }

    private function checkAndShortlistApplicant(array $applicantResult, string $stream)
    {
        $qualified = $admitted = false;
        if ($applicantResult['feed']['program_category'] == 'degree') {
            if ($applicantResult['feed']['program_group'] == 'A') {
                if ($applicantResult['feed']['program_type'] == 1) {
                    if ($applicantResult['feed']['required_core_passed'] == 3 && $applicantResult['feed']['required_elective_passed'] == 1 && count($applicantResult['feed']['additional_elective_subjects']) >= 2) {
                        if ($applicantResult['mode'] == 'WASSCE' && $applicantResult['feed']['total_score'] <= 36) {
                            $qualified = true;
                        } elseif ($applicantResult['mode'] == 'SSSCE' && $applicantResult['feed']['total_score'] <= 24) {
                            $qualified = true;
                        }

                        if ($qualified) {
                            $status = $this->shortlistApplicant($applicantResult['app_id'], $applicantResult['prog_id'], $stream, 100, true, true);
                            if (!empty($status)) {
                                if ($status["success"]) {
                                    $admitted = true;
                                }
                            }
                            return array_merge($status, ["qualified" => $qualified, "admitted" => $admitted]);
                        }
                        return ["qualified" => $qualified, "admitted" => $admitted];
                    } else {
                        return ["qualified" => $qualified, "admitted" => $admitted];
                    }
                } else if ($applicantResult['feed']['program_type'] == 2) {
                    if ($applicantResult['feed']['required_core_passed'] >= 3 && $applicantResult['feed']['required_elective_passed'] >= 2 && count($applicantResult['feed']['additional_elective_subjects']) >= 1) {
                        if ($applicantResult['mode'] == 'WASSCE' && $applicantResult['feed']['total_score'] <= 36) {
                            $qualified = true;
                        } elseif ($applicantResult['mode'] == 'SSSCE' && $applicantResult['feed']['total_score'] <= 24) {
                            $qualified = true;
                        }

                        if ($qualified) {
                            $status = $this->shortlistApplicant($applicantResult['app_id'], $applicantResult['prog_id'], $stream, 100, true, true);
                            if (!empty($status)) {
                                if ($status["success"]) {
                                    $admitted = true;
                                }
                            }
                            return array_merge($status, ["qualified" => $qualified, "admitted" => $admitted]);
                        }
                        return ["qualified" => $qualified, "admitted" => $admitted];
                    } else {
                        return ["qualified" => $qualified, "admitted" => $admitted];
                    }
                }
            } else if ($applicantResult['feed']['program_group'] == 'B') {
                if ($applicantResult['feed']['required_core_passed'] >= 3 && count($applicantResult['feed']['additional_elective_subjects']) >= 3) {
                    if ($applicantResult['mode'] == 'WASSCE' && $applicantResult['feed']['total_score'] <= 36) {
                        $qualified = true;
                    } elseif ($applicantResult['mode'] == 'SSSCE' && $applicantResult['feed']['total_score'] <= 24) {
                        $qualified = true;
                    }

                    if ($qualified) {
                        $status = $this->shortlistApplicant($applicantResult['app_id'], $applicantResult['prog_id'], $stream, 100, true, true);
                        if (!empty($status)) {
                            if ($status["success"]) {
                                $admitted = true;
                            }
                        }
                        return array_merge($status, ["qualified" => $qualified, "admitted" => $admitted]);
                    }
                    return ["qualified" => $qualified, "admitted" => $admitted];
                } else {
                    return ["qualified" => $qualified, "admitted" => $admitted];
                }
            }
        } else if ($applicantResult['feed']['program_category'] == 'diploma') {
            if ($applicantResult['feed']['program_group'] == 'A') {
                if ($applicantResult['feed']['program_type'] == 1) {
                    if ($applicantResult['feed']['required_core_passed'] == 3 && $applicantResult['feed']['required_elective_passed'] == 1 && count($applicantResult['feed']['additional_elective_subjects']) >= 2) {
                        if ($applicantResult['mode'] == 'WASSCE' && $applicantResult['feed']['total_score'] <= 42) {
                            $qualified = true;
                        } elseif ($applicantResult['mode'] == 'SSSCE' && $applicantResult['feed']['total_score'] <= 24) {
                            $qualified = true;
                        }

                        if ($qualified) {
                            $status = $this->shortlistApplicant($applicantResult['app_id'], $applicantResult['prog_id'], $stream, 100, true, true);
                            if (!empty($status)) {
                                if ($status["success"]) {
                                    $admitted = true;
                                }
                            }
                            return array_merge($status, ["qualified" => $qualified, "admitted" => $admitted]);
                        }
                        return ["qualified" => $qualified, "admitted" => $admitted];
                    } else {
                        return ["qualified" => $qualified, "admitted" => $admitted];
                    }
                } else if ($applicantResult['feed']['program_type'] == 2) {
                    if ($applicantResult['feed']['required_core_passed'] >= 3 && $applicantResult['feed']['required_elective_passed'] >= 2 && count($applicantResult['feed']['additional_elective_subjects']) >= 1) {
                        if ($applicantResult['mode'] == 'WASSCE' && $applicantResult['feed']['total_score'] <= 42) {
                            $qualified = true;
                        } elseif ($applicantResult['mode'] == 'SSSCE' && $applicantResult['feed']['total_score'] <= 24) {
                            $qualified = true;
                        }

                        if ($qualified) {
                            $status = $this->shortlistApplicant($applicantResult['app_id'], $applicantResult['prog_id'], $stream, 100, true, true);
                            if (!empty($status)) {
                                if ($status["success"]) {
                                    $admitted = true;
                                }
                            }
                            return array_merge($status, ["qualified" => $qualified, "admitted" => $admitted]);
                        }
                        return ["qualified" => $qualified, "admitted" => $admitted];
                    } else {
                        return ["qualified" => $qualified, "admitted" => $admitted];
                    }
                }
            } else if ($applicantResult['feed']['program_group'] == 'B') {
                if ($applicantResult['feed']['required_core_passed'] >= 3 && count($applicantResult['feed']['additional_elective_subjects']) >= 3) {
                    if ($applicantResult['mode'] == 'WASSCE' && $applicantResult['feed']['total_score'] <= 42) {
                        $qualified = true;
                    } elseif ($applicantResult['mode'] == 'SSSCE' && $applicantResult['feed']['total_score'] <= 24) {
                        $qualified = true;
                    }

                    if ($qualified) {
                        $status = $this->shortlistApplicant($applicantResult['app_id'], $applicantResult['prog_id'], $stream, 100, true, true);
                        if (!empty($status)) {
                            if ($status["success"]) {
                                $admitted = true;
                            }
                        }
                        return array_merge($status, ["qualified" => $qualified, "admitted" => $admitted]);
                    }
                    return ["qualified" => $qualified, "admitted" => $admitted];
                } else {
                    return ["qualified" => $qualified, "admitted" => $admitted];
                }
            }
        }
    }

    private function getAppProgDetailsByAppID($appID)
    {
        $sql = "SELECT * FROM `program_info` WHERE `app_login` = :i";
        return $this->dm->getData($sql, array(':i' => $appID));
    }

    private function getApplicantContactInfo($appID)
    {
        $sql = "SELECT al.`id`, pd.`app_number`, pi.`prefix` , pi.`first_name`, pi.`middle_name`, pi.`last_name`, 
                pi.`suffix`, pi.`gender`, pi.`dob`, pi.`marital_status`, pi.`nationality`, pi.`disability`, 
                pi.`photo`, pi.`phone_no1_code`, pi.`phone_no1`, pi.`email_addr` 
                FROM `personal_information` AS pi, `applicants_login` AS al, `purchase_detail` AS pd 
                WHERE pi.`app_login` = al.`id` AND al.`purchase_id` = pd.`id` AND al.`id` = :i";
        return $this->dm->getData($sql, array(':i' => $appID));
    }

    public function sendAppAdmissionStatus($appID, $prog_choice): mixed
    {
        $contactInfo = $this->getApplicantContactInfo($appID);
        $programInfo = $this->getAppProgDetailsByAppID($appID);

        // Prepare SMS message
        $message = 'Hi ' . ucfirst(strtolower($contactInfo[0]["first_name"])) . " " . ucfirst(strtolower($contactInfo[0]["last_name"])) . '. ';
        $message .= 'Congratulations! You have been offered admission into Regional Maritime University to read ' . $programInfo[0][$prog_choice];
        $message .= ' as a ' . $programInfo[0]['study_stream'] . " student. To secure this offer, please ";
        $message .= 'visit the application portal at https://admissions.rmuictonline.com and login to complete an acceptance form.';
        $to = $contactInfo[0]["phone_no1_code"] . $contactInfo[0]["phone_no1"];

        $sentEmail = false;
        $smsSent = false;

        // Send SMS message
        $response = json_decode($this->expose->sendSMS($to, $message));

        // Set SMS response status
        if (!$response->status) $smsSent = true;

        // Check if email address was provided
        if (!empty($contactInfo[0]["email_address"])) {
            // Prepare email message
            $e_message = '<p>Hi ' . $contactInfo[0]["first_name"] . ",</p>";
            $e_message .= '<p>Congratulations! You have been offered admission into Regional Maritime University to read ' . $programInfo[0][$prog_choice];
            $e_message .= 'as a ' . strtolower($programInfo[0]['study_stream']) . ' student.</p>';
            $e_message .= '<p>To secure this offer, please visit the application portal at https://admissions.rmuictonline.com and login to complete an acceptance form.';

            // Send email message
            $e_response = $this->expose->sendEmail($contactInfo[0]["email_addr"], 'ONLINE APPLICATION PORTAL LOGIN INFORMATION', $e_message);

            // Ste email reponse status
            if ($e_response) $sentEmail = true;
        }

        // Set output message
        $output = "";
        if ($smsSent || $sentEmail) $output = "Applicant admitted successfully and SMS/email sent!";
        else $output = "Applicant admitted successfully but failed to send SMS/Email!";

        // Log activity
        $this->logActivity(
            $_SESSION["user"],
            "INSERT",
            "Admissions user {$_SESSION["user"]} admitted applicant with ID {$appID}"
        );

        // return output message
        return array("success" => true, "message" => $output);
    }

    public function fetchApplicantPersInfoByAppID($appID): mixed
    {
        return $this->dm->getData("SELECT * FROM `personal_information` WHERE app_login = :i", array(":i" => $appID));
    }

    public function fetchApplicantProgInfoByAppID($appID): mixed
    {
        return $this->dm->getData("SELECT * FROM `program_info` WHERE app_login = :i", array(":i" => $appID));
    }

    public function fetchApplicantProgInfoByProgName($progName): mixed
    {
        return $this->dm->getData("SELECT p.*, f.`name` AS form_type FROM `programs` AS p, `forms` AS f 
        WHERE p.`name` = :n AND p.`type` = f.`id`", array(":n" => $progName));
    }

    public function fetchApplicantProgInfoByProgID($progID): mixed
    {
        return $this->dm->getData("SELECT * FROM `programs` WHERE `id` = :i", array(":i" => $progID));
    }

    public function fetchAdmissionLetterData(): mixed
    {
        return $this->dm->getData("SELECT * FROM `admission_letter_data` WHERE `in_use` = 1");
    }

    // public function fetchAdmissionLetterData(): mixed
    // {
    //     return $this->dm->getData("SELECT * FROM `admission_letter_data` WHERE `in_use` = 1");
    // }

    // public function fetchAdmissionLetterData(): mixed
    // {
    //     return $this->dm->getData("SELECT * FROM `admission_letter_data` WHERE `in_use` = 1");
    // }

    // public function fetchAdmissionLetterData(): mixed
    // {
    //     return $this->dm->getData("SELECT * FROM `admission_letter_data` WHERE `in_use` = 1");
    // }

    public function fetchApplicantAppNumber(int $appID): mixed
    {
        return $this->dm->getData("SELECT pd.`app_number` FROM `purchase_detail` AS pd, applicants_login AS al 
        WHERE al.purchase_id = pd.id AND al.id = :i", array(":i" => $appID));
    }

    public function checkProgramStreamAvailability($progName, $stream_applied): mixed
    {
        $prog_info = $this->fetchApplicantProgInfoByProgName($progName)[0];

        if ($prog_info["weekend"] == 0) {
            if (strtolower($stream_applied) === "weekend") {
                return [
                    "success" => false,
                    "data" => $prog_info["id"],
                    "message" => "Selected program is not available for {$stream_applied} stream!"
                ];
            }
        }

        if ($prog_info["regular"] == 0) {
            if (strtolower($stream_applied) === "regular") {
                return [
                    "success" => false,
                    "data" => $prog_info["id"],
                    "message" => "Selected program is not available for {$stream_applied} stream!"
                ];
            }
        }

        return [
            "success" => true,
            "data" => $prog_info["id"],
            "message" => "Selected program is available for {$stream_applied} stream!"
        ];
    }

    private function generateApplicantAdmissionLetter1($letter_data, $letter_type = "undergrade", $admission_period = []): mixed
    {
        try {
            $dir_path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'admission_letters' . DIRECTORY_SEPARATOR;
            if (!is_dir($dir_path)) mkdir($dir_path, 0755, true);

            $template_processor = new TemplateProcessor($dir_path . $letter_type . '_template.docx');

            $temp_parent = $dir_path . strtolower($admission_period["intake"]) . DIRECTORY_SEPARATOR;
            if (!is_dir($temp_parent)) mkdir($temp_parent, 0755, true);

            $sub_parent = $temp_parent . strtolower($admission_period["academic_year"]) . DIRECTORY_SEPARATOR;
            if (!is_dir($sub_parent)) mkdir($sub_parent, 0755, true);

            $temp_path = $sub_parent . strtolower($admission_period["semester"]) . DIRECTORY_SEPARATOR;
            if (!is_dir($temp_path)) mkdir($temp_path, 0755, true);

            $template_processor->setValues($letter_data);
            $template_processor->saveAs($temp_path . "{$letter_data['app_number']}.docx");

            return array("success" => true, "message" => "Admission letter successfully generated!");
        } catch (\Exception $e) {
            return array("success" => false, "message" => $e->getMessage());
        }
    }

    private function generateApplicantAdmissionLetter($letter_data, $program, $letter_type = "undergrade", $admission_period = []): mixed
    {
        try {
            $dir_path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'admission_letters' . DIRECTORY_SEPARATOR;

            // Create directories recursively
            $academic_year_path = $dir_path . strtolower($admission_period["academic_year"]) . DIRECTORY_SEPARATOR;
            $intake_path = $academic_year_path . strtolower($admission_period["intake"]) . DIRECTORY_SEPARATOR;
            $program_category = $intake_path . strtolower($letter_data['Program_Type']) . DIRECTORY_SEPARATOR;
            $stream_applied = $program_category . strtolower($letter_data['Program_Stream']) . DIRECTORY_SEPARATOR;
            $program_applied = $stream_applied . strtolower($program) . DIRECTORY_SEPARATOR;

            foreach ([$academic_year_path, $intake_path, $program_category, $stream_applied, $program_applied] as $path) {
                if (!is_dir($path)) mkdir($path, 0755, true);
            }
            $mpdf = new \Mpdf\Mpdf();

            // if (strtolower($letter_data['department_name']) === "ict")
            //     $html = file_get_contents($dir_path . "ict_" . $letter_type . '_template.html');
            // else
            //     $html = file_get_contents($dir_path . $letter_type . '_template.html');

            $html = file_get_contents($dir_path . $letter_type . '_template.php');
            // Perform placeholder replacement
            $html = str_replace('${Letter_Reference}', 'RMU/2024/001', $html);
            //$html = str_replace('${Letter_Reference}', $letter_data['Letter_Reference'], $html);
            $html = str_replace('${Letter_Date}', date('F j, Y'), $html);
            $html = str_replace('${Full_Name}', $letter_data['Full_Name'], $html);
            $html = str_replace('${Box_Location}', $letter_data['Box_Location'], $html);
            $html = str_replace('${Box_Address}', $letter_data['Box_Address'], $html);
            $html = str_replace('${Location}', $letter_data['Location'], $html);
            $html = str_replace('${Program_Length_1}', $letter_data['Program_Length_1'], $html);
            $html = str_replace('${Program_Offered_1}', $letter_data['Program_Offered_1'], $html);
            $html = str_replace('${Year_of_Admission}', $letter_data['Year_of_Admission'], $html);
            $html = str_replace('${Program_Type}', $letter_data['Program_Type'], $html);
            $html = str_replace('${No_of_Semesters}', $letter_data['No_of_Semesters'], $html);
            $html = str_replace('${Program_Stream}', $letter_data['Program_Stream'], $html);
            $html = str_replace('${Program_Offered_2}', $letter_data['Program_Offered_2'], $html);
            $html = str_replace('${Program_Length_2}', $letter_data['Program_Length_1'], $html);
            $html = str_replace('${Program_Type}', $letter_data['Program_Type'], $html);
            $html = str_replace('${Commencement_Date}', $letter_data['Commencement_Date'], $html);
            $html = str_replace('${Initial_Fees_in_Words}', $letter_data['Initial_Fees_in_Words'], $html);
            $html = str_replace('${Initial_Fees_in_Figures}', $letter_data['Initial_Fees_in_Figures'], $html);
            $html = str_replace('${Tel_Number_1}', $letter_data['Tel_Number_1'], $html);
            $html = str_replace('${Tel_Number_2}', $letter_data['Tel_Number_2'], $html);
            $html = str_replace('${Closing_Date}', $letter_data['Closing_Date'], $html);
            $html = str_replace('${Orientation_Date}', $letter_data['Orientation_Date'], $html);
            $html = str_replace('${Deadline_Date}', $letter_data['Deadline_Date'], $html);
            $html = str_replace('${Registration_Fees_in_Words}', $letter_data['Registration_Fees_in_Words'], $html);
            $html = str_replace('${Registration_Fees_in_Figures}', $letter_data['Registration_Fees_in_Figures'], $html);
            $html = str_replace('${University_Registrar}', $letter_data['University_Registrar'], $html);

            $mpdf->DefHTMLHeaderByName(
                'Chapter2Header',
                '<div style="text-align: right; border-bottom: 1px solid #000000; 
                font-weight: bold; font-size: 10pt;">Chapter 2</div>'
            );

            $mpdf->DefHTMLFooterByName(
                'Chapter2Footer',
                '<div style="text-align: right; font-weight: bold; font-size: 8pt; 
                font-style: italic;">Chapter 2 Footer</div>'
            );

            $mpdf->WriteHTML($html);
            $pdf_output_path = $program_applied . "{$letter_data['app_number']}.pdf";
            $mpdf->Output($pdf_output_path, 'F');

            return [
                "success" => true,
                "message" => "Admission letter successfully generated!",
                "acceptance_form_path" => $dir_path . "acceptance_form.docx",
                //"letter_word_path" => $word_output_path,
                "letter_pdf_path" => $pdf_output_path
            ];
        } catch (Exception $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

    private function loadApplicantAdmissionLetterData($appID, $prog_id, $stream_applied, $level): mixed
    {
        $app_pers_info = $this->fetchApplicantPersInfoByAppID($appID)[0];
        $app_app_number = $this->fetchApplicantAppNumber($appID)[0];
        $admission_period = $this->fetchCurrentAdmissionPeriod()[0];
        $static_letter_data = $this->fetchAdmissionLetterData()[0];
        $prog_info = $this->fetchAllFromProgramByID($prog_id)[0];
        // $static_letter_data = [];
        $is_member = in_array($app_pers_info["nationality"], ["CAMEROON", "GAMBIA", "GHANA", "LIBERIA", "SIERRA LEONE"]);

        // if ($is_member) {
        //     if (strtolower($prog_info["department_name"]) == "ict") {
        //         $static_letter_data = $this->fetchMemberICTAdmissionLetterData()[0];
        //     } else {
        //         $static_letter_data = $this->fetchMemberAdmissionLetterData()[0];
        //     }
        // } else {
        //     if (strtolower($prog_info["department_name"]) == "ict") {
        //         $static_letter_data = $this->fetchNonMemberICTAdmissionLetterData()[0];
        //     } else {
        //         $static_letter_data = $this->fetchNonMemberAdmissionLetterData()[0];
        //     }
        // }

        $letter_data = [];

        switch ($prog_info["code"]) {
            case 'BSC':
            case 'DIPLOMA':

                $program_name = ($prog_info["category"] === "DIPLOMA") ? str_replace(["diploma in ", "diploma "], "", strtolower($prog_info["name"])) : $prog_info["name"];
                $program_name = ($prog_info["category"] === "DEGREE") ? str_replace(["b.sc ", "b.sc. ", "b.s.c ", "b.s.c. "], "", strtolower($prog_info["name"])) : $prog_info["name"];
                $program_name = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], " ", $program_name);

                if ($level == 100) $program_dur = $prog_info["duration"];
                else if ($level > 100) $program_dur = ((400 - $level) / 100) + 1;

                $letter_data = [
                    "success" => true,
                    "type" => "undergrade",
                    "period" => $admission_period,
                    "program" => $program_name,
                    "phone_number" => $app_pers_info["phone_no1_code"] . $app_pers_info["phone_no1"],
                    "email_address" => $app_pers_info["email_addr"],
                    "program_dur" => $program_dur,
                    "level_admitted" => $level,
                    "data" => [
                        "department" => $prog_info["department_name"],
                        'app_number' => $app_app_number["app_number"],
                        'Prefix' => ucwords(strtolower($app_pers_info["prefix"])),
                        'First_Name' => ucwords(strtolower($app_pers_info["first_name"])),
                        'Middle_Name' => !empty($app_pers_info["middle_name"]) ? ucwords(strtolower($app_pers_info["middle_name"])) : '',
                        'Last_Name' => ucwords(strtolower($app_pers_info["last_name"])),
                        'Full_Name' => ucwords(strtolower(!empty($app_pers_info["middle_name"]) ? $app_pers_info["first_name"] . " " . $app_pers_info["middle_name"] . " " .  $app_pers_info["last_name"] : $app_pers_info["first_name"] . " " . $app_pers_info["last_name"])),
                        'Box_Location' => ucwords(strtolower($app_pers_info["postal_town"] . " - " . $app_pers_info["postal_spr"])),
                        'Box_Address' => ucwords(strtolower($app_pers_info["postal_addr"])),
                        'Location' => ucwords(strtolower($app_pers_info["postal_country"])),
                        'Year_of_Admission' => $admission_period["academic_year"],
                        'Program_Length_1' => strtoupper(strtolower($program_dur . "-" . $prog_info["dur_format"])),
                        'Program_Offered_1' => strtoupper(strtolower($prog_info["name"])),
                        'Program_Length_2' => strtolower($program_dur . "-" . $prog_info["dur_format"]),
                        'Program_Offered_2' => (($prog_info["category"] === "DEGREE") ? "B.Sc " : "") . ucwords(strtolower($program_name)),
                        'Program_Type' => ucwords(strtolower($prog_info["category"])),
                        'Program_Stream' => trim(strtolower($stream_applied)),
                        'No_of_Semesters' => $prog_info["num_of_semesters"] . " semesters",
                        'Commencement_Date' => (new \DateTime($static_letter_data["commencement_date"]))->format("l F j, Y"),
                        'Initial_Fees_in_Words' => $static_letter_data["initial_fees_in_words"],
                        'Initial_Fees_in_Figures' => $static_letter_data["initial_fees_in_figures"],
                        'Tel_Number_1' => $static_letter_data["tel_number_1"],
                        'Tel_Number_2' => $static_letter_data["tel_number_2"],
                        'Closing_Date' => (new \DateTime($static_letter_data["closing_date"]))->format("l F j, Y"),
                        'Orientation_Date' => (new \DateTime($static_letter_data["orientation_date"]))->format("l F j, Y"),
                        'Deadline_Date' => (new \DateTime($static_letter_data["deadline_date"]))->format("l F j, Y"),
                        'Registration_Fees_in_Words' => $static_letter_data["registration_fees_in_words"],
                        'Registration_Fees_in_Figures' => $static_letter_data["registration_fees_in_figures"],
                        'University_Registrar' => $static_letter_data["university_registrar"],
                        'Program_Code' => $prog_info["code"],
                        'Program_Faculty' => ucwords(strtolower($prog_info["faculty"])),
                        'Program_Merit' => ucwords(strtolower($prog_info["merit"])),
                        'Program_Duration' => $prog_info["duration"],
                        'Program_Dur_Format' => ucwords(strtolower($prog_info["dur_format"]))
                    ]
                ];
                break;

            case 'MSC':
            case 'MA':

                $program_name = ($prog_info["category"] === "MASTERS") ? str_replace(["m.sc ", "m.sc. ", "m.s.c ", "m.s.c. ", "m.a ", "m.a. "], "", strtolower($prog_info["name"])) : $prog_info["name"];
                $program_name = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], " ", $program_name);

                $letter_data = [
                    "success" => true,
                    "type" => "postgrade",
                    "period" => $admission_period,
                    "program" => $program_name,
                    "phone_number" => $app_pers_info["phone_no1_code"] . $app_pers_info["phone_no1"],
                    "email_address" => $app_pers_info["email_addr"],
                    "data" => [
                        'app_number' => $app_app_number["app_number"],
                        'First_Name' => ucwords(strtolower($app_pers_info["first_name"])),
                        'Middle_Name' => ucwords(strtolower($app_pers_info["middle_name"])),
                        'Last_Name' => ucwords(strtolower($app_pers_info["last_name"])),
                        'Full_Name' => ucwords(strtolower(!empty($app_pers_info["middle_name"]) ? $app_pers_info["first_name"] . " " . $app_pers_info["middle_name"] . " " .  $app_pers_info["last_name"] : $app_pers_info["first_name"] . " " . $app_pers_info["last_name"])),
                        'Box_Location' => ucwords(strtolower($app_pers_info["postal_town"] . " - " . $app_pers_info["postal_spr"])),
                        'Box_Address' => ucwords(strtolower($app_pers_info["postal_addr"])),
                        'Location' => ucwords(strtolower($app_pers_info["postal_country"])),
                        'Year_of_Admission' => $admission_period["academic_year"],
                        'Program_Length_1' => strtoupper(strtolower($prog_info["duration"] . "-" . $prog_info["dur_format"])),
                        'Program_Offered_1' => strtoupper(strtolower($prog_info["name"])),
                        'Program_Length_2' => strtolower($prog_info["duration"] . "-" . $prog_info["dur_format"]),
                        'Program_Offered_2' => (($prog_info["code"] === "MSC") ? "M.Sc " : "M.A ") . ucwords(strtolower($program_name)),
                        'Program_Type' => ucwords(strtolower($prog_info["category"])),
                        'Program_Stream' => strtolower($stream_applied),
                        'No_of_Semesters' => $prog_info["num_of_semesters"] . " semesters",
                        'Commencement_Date' => (new \DateTime($static_letter_data["commencement_date"]))->format("l F j, Y"),
                        'Initial_Fees_in_Words' => $static_letter_data["initial_fees_in_words"],
                        'Initial_Fees_in_Figures' => $static_letter_data["initial_fees_in_figures"],
                        'Tel_Number_1' => $static_letter_data["tel_number_1"],
                        'Tel_Number_2' => $static_letter_data["tel_number_2"],
                        'Closing_Date' => (new \DateTime($static_letter_data["closing_date"]))->format("l F j, Y"),
                        'Orientation_Date' => (new \DateTime($static_letter_data["orientation_date"]))->format("l F j, Y"),
                        'Deadline_Date' => (new \DateTime($static_letter_data["deadline_date"]))->format("l F j, Y"),
                        'Registration_Fees_in_Words' => $static_letter_data["registration_fees_in_words"],
                        'Registration_Fees_in_Figures' => $static_letter_data["registration_fees_in_figures"],
                        'University_Registrar' => $static_letter_data["university_registrar"],
                        'Program_Code' => $prog_info["code"],
                        'Program_Faculty' => ucwords(strtolower($prog_info["faculty"])),
                        'Program_Merit' => ucwords(strtolower($prog_info["merit"])),
                        'Program_Duration' => $prog_info["duration"],
                        'Program_Dur_Format' => ucwords(strtolower($prog_info["dur_format"]))
                    ]
                ];
                break;

            case 'UPGRADE':
                $letter_data = [
                    "success" => true,
                    "type" => "upgrade",
                    "period" => $admission_period,
                    "phone_number" => $app_pers_info["phone_no1_code"] . $app_pers_info["phone_no1"],
                    "email_address" => $app_pers_info["email_addr"],
                    "data" => [
                        'app_number' => $app_app_number["app_number"],
                        'First_Name' => ucwords(strtolower($app_pers_info["first_name"])),
                        'Middle_Name' => ucwords(strtolower($app_pers_info["middle_name"])),
                        'Last_Name' => ucwords(strtolower($app_pers_info["last_name"])),
                        'Full_Name' => !empty($app_pers_info["middle_name"]) ? $app_pers_info["first_name"] . " " . $app_pers_info["middle_name"] .  " " .  $app_pers_info["last_name"] : $app_pers_info["first_name"] . " " . $app_pers_info["last_name"],
                        'Box_Location' => $app_pers_info["postal_town"] . " " . $app_pers_info["postal_spr"],
                        'Box_Address' => $app_pers_info["postal_addr"],
                        'Location' => $app_pers_info["postal_country"],
                        'Year_of_Admission' => $admission_period["academic_year"],
                        'Program_Length_1' => $prog_info["duration"] . "-" . $prog_info["dur_format"],
                        'Program_Offered_1' => $prog_info["name"],
                        'Program_Length_2' => $prog_info["duration"] . "-" . $prog_info["dur_format"],
                        'Program_Offered_2' => ucwords($prog_info["name"]),
                        'Program_Type' => ucwords($prog_info["form_type"]),
                        'Program_Stream' => strtolower($stream_applied),
                        'No_of_Semesters' => $prog_info["num_of_semesters"] . " semesters",
                        'Commencement_Date' => (new \DateTime($letter_data["commencement_date"]))->format("l F j, Y"),
                        'Initial_Fees_in_Words' => $letter_data["initial_fees_in_words"],
                        'Initial_Fees_in_Figures' => $letter_data["initial_fees_in_figures"],
                        'Tel_Number_1' => $letter_data["tel_number_1"],
                        'Tel_Number_2' => $letter_data["tel_number_2"],
                        'Closing_Date' => (new \DateTime($letter_data["closing_date"]))->format("l F j, Y"),
                        'Orientation_Date' => (new \DateTime($letter_data["orientation_date"]))->format("l F j, Y"),
                        'Deadline_Date' => (new \DateTime($letter_data["deadline_date"]))->format("l F j, Y"),
                        'Registration_Fees_in_Words' => $letter_data["registration_fees_in_words"],
                        'Registration_Fees_in_Figures' => $letter_data["registration_fees_in_figures"],
                        'University_Registrar' => $letter_data["university_registrar"],
                        'Program_Code' => $prog_info["code"],
                        'Program_Faculty' => ucwords(strtolower($prog_info["faculty"])),
                        'Program_Merit' => ucwords(strtolower($prog_info["merit"])),
                        'Program_Duration' => $prog_info["duration"],
                        'Program_Dur_Format' => ucwords(strtolower($prog_info["dur_format"]))
                    ]
                ];
                break;

            default:
                $letter_data = [
                    "success" => false,
                    "message" => ["There's no letter format set for the applied program category!"]
                ];
                break;
        }
        return $letter_data;
    }

    private function updateApplicantAdmissionStatus($app_id, $prog_id, $program_dur, $level, $stream, $extras = []): mixed
    {
        $extras_query = "";
        if (!empty($extras)) {
            $extras_query = "`emailed_letter` = 1, `notified_sms` = 1, ";
        } else {
            if (isset($extras["emailed_letter"])) {
                if (empty($extras["emailed_letter"])) $extras_query .= "`emailed_letter` = 0, ";
                else $extras_query .= "`emailed_letter` = 1, ";
            }
            if (isset($extras["notified_sms"])) {
                if (empty($extras["notified_sms"])) $extras_query .= "`notified_sms` = 0, ";
                else $extras_query .= "`notified_sms` = 1, ";
            }
        }
        $query = "UPDATE `form_sections_chek` 
        SET `admitted` = 1, `declined` = 0,{$extras_query} `programme_awarded` = :p, `programme_duration` = :pr, `level_admitted` = :l , `stream_admitted` = :s 
        WHERE `app_login` = :i";
        return ($this->dm->inputData($query, array(":i" => $app_id, ":p" => $prog_id, ":pr" => $program_dur, ":l" => $level, ":s" => $stream)));
    }

    private function sendAdmissionLetterViaEmail($data, $file_paths = []): mixed
    {
        //return $data;
        $pmd = match ($data["data"]["Program_Code"]) {
            "BSC" => ["Bachelor of Science", "B.Sc."],
            "DIPLOMA" => ["Diploma", "Diploma"],
            "MSC" => ["Master of Science", "M.Sc."],
            "MA" => ["Master of Art", "M.A."]
        };

        $dur_word = match ($data["data"]["Program_Duration"]) {
            '1' => 'One',
            '2' => 'Two',
            '3' => 'Three',
            '4' => 'Four',
            '5' => 'Five',
            '6' => 'Six',
            '7' => 'Seven',
            '8' => 'Eight',
            '9' => 'Nine',
            '10' => 'Ten'
        };

        $email = $data["email_address"];
        $subject = "Admission to Regional Maritime University";
        $message = "<p>Dear " . $data["data"]["Prefix"] . " " . $data["data"]["First_Name"] . " " . $data["data"]["Last_Name"] . ",</p>";
        $message .= "<p>Compliments from the School of Undergraduate Studies (SUS)";
        $message .= "<p>The School of Undergraduate Studies on behalf of the Academic Council of the University is pleased to offer ";
        $message .= "you admission to pursue a {$dur_word} ({$data["data"]["Program_Duration"]}) {$data["data"]["Program_Dur_Format"]} <strong>{$data["data"]["Program_Stream"]}</strong> ";
        $message .= "{$pmd[0]} ({$pmd[1]}) programme in {$data["data"]["Program_Merit"]} in the Faculty of ";
        $message .= "{$data["data"]["Program_Faculty"]} of the University. </p>";
        $message .= "<p>Kindly find attached a copy of your admission letter, alongside the fees payment details and a copy of the acceptance form. ";
        $message .= "The Original copy of the attached documents are available at the University's Registry Office for collection ";
        $message .= "from <strong>Monday to Friday between the hours of 9a.m. to 3p.m.</strong></p>";
        $message .= "<p>Do not hesitate to contact <a href='mailto:admission@rmu.edu.gh'>admission@rmu.edu.gh</a> for any clarification.</p>";
        $message .= "<p>Congratulations on your enrollment to the Regional Maritime University.</p>";
        $message .= "<p>Thank you and warm regards.</p>";

        $response = $this->expose->sendEmail($email, $subject, $message, $file_paths);
        if (!empty($response) && is_int($response)) return 1;
        return 0;
    }

    private function notifyApplicantViaSMS($data): mixed
    {
        $to = $data["phone_number"];
        $message = "Congratulations! You have been admitted to Regional Maritime University to pursue {$data["data"]["Program_Offered_2"]}. ";
        $message .= "Kindly log on to the Admission Portal for more details.";
        $response = json_decode($this->expose->sendSMS($to, $message));
        if (!$response->status) return 1;
        return 0;
    }

    public function admitIndividualApplicant($appID, $prog_id, $stream_applied, $level, bool $email_letter = false, bool $sms_notify = false)
    {
        $l_res = $this->loadApplicantAdmissionLetterData($appID, $prog_id, $stream_applied, $level);
        if (!$l_res["success"]) return $l_res;

        $g_res = $this->generateApplicantAdmissionLetter($l_res["data"], $l_res["program"], $l_res["type"], $l_res["period"]);
        if (!$g_res["success"]) return $g_res;

        $file_paths = [];
        array_push($file_paths, $g_res["letter_pdf_path"], $g_res["acceptance_form_path"]);
        $status_update_extras = [];

        //if ($email_letter) $status_update_extras["emailed_letter"] = $this->sendAdmissionLetterViaEmail($l_res, $file_paths);
        if ($sms_notify) $status_update_extras["notified_sms"] = $this->notifyApplicantViaSMS($l_res);

        $u_res = $this->updateApplicantAdmissionStatus($appID, $prog_id, $l_res["program_dur"], $l_res["level_admitted"], $stream_applied, $status_update_extras);
        if (!$u_res) return array("success" => false, "message" => "Failed to admit applicant!");
        return array("success" => true, "message" => "Successfully admitted applicant!");
    }

    public function declineIndividualApplicant($appID)
    {
        $query = "UPDATE `form_sections_chek` SET `admitted` = 0, `declined` = 1  WHERE `app_login` = :i";
        if ($this->dm->inputData($query, array(":i" => $appID))) {
            return array("success" => true, "message" => "Succesfully declined applicant admission!");
        }
        return array("success" => false, "message" => "Failed to declined applicant admission!");
    }

    public function updateApplicationStatus($appID, $statusName, $statusState)
    {
        $query = "UPDATE `form_sections_chek` SET `$statusName` = :ss WHERE `app_login` = :i";
        return $this->dm->inputData($query, array(":i" => $appID, ":ss" => $statusState));
    }

    public function fetchAllFromProgramWithDepartByProgID($prog_id)
    {
        $query = "SELECT pg.*, dp.`id` AS department_id, dp.`name` AS department_name 
        FROM `programs` AS pg, `department` AS dp WHERE pg.`id` = :i AND pg.`department` = dp.`id`";
        return $this->dm->getData($query, array(":i" => $prog_id));
    }

    public function sendAdmissionFiles($appID, $fileObj)
    {
        return 1;
    }

    private function getAdmissionPeriodYearsByID($admin_period): mixed
    {
        return $this->dm->getData(
            "SELECT *, YEAR(`start_date`) AS sYear, YEAR(`end_date`) AS eYear FROM admission_period WHERE id = :i",
            array(":i" => $admin_period)
        );
    }

    private function getTotalEnrolledApplicants($prog_id, $academic_year_id, $stream): mixed
    {
        return $this->dm->getData(
            "SELECT COUNT(s.`app_number`) AS total FROM `student` AS s, `academic_year` AS a, `programs` AS p 
            WHERE p.`id` = s.`fk_program` AND a.`id` = s.`fk_academic_year` AND 
            s.`fk_program` = :p AND s.`fk_academic_year` = :a AND s.`stream_admitted` = :s",
            array(":p" => $prog_id, ":a" => $academic_year_id, ":s" => $stream)
        )[0]["total"];
    }

    private function resolveClassByProgram($prog_id, $class_code = ''): mixed
    {
        if (empty($class_code)) return array("success" => false, "message" => "Couldn't resolve student class!");
        $class = $this->dm->getData("SELECT `code` FROM `class` WHERE `code` = :c", array(":c" => $class_code));
        if (!empty($class)) return array("success" => true, "message" => $class_code);
        $query = "INSERT INTO `class` (`code`, `fk_program`) VALUES (:c, :fkp)";
        $added = $this->dm->inputData($query, array(':c' => $class_code, ':fkp' => $prog_id));
        if (empty($added)) return  array("success" => false, "message" => "Couldn't add/create a new class for student!");
        return array("success" => true, "message" => $class_code);
    }

    private function createUndergradStudentIndexNumber($appID, $progID, $term_admitted): mixed
    {
        $prog_data = $this->fetchAllFromProgramWithDepartByProgID($progID);
        if (empty($prog_data)) return array(
            "success" => false,
            "message" => "Process terminated! Couldn't fetch applicant's program data"
        );

        $adminPeriodYear = $this->getAdmissionPeriodYearsByID($_SESSION["admin_period"]);

        $startYear = (int) substr($adminPeriodYear[0]["sYear"], -2);
        if ($prog_data[0]["dur_format"] == "year") $completionYear = $startYear +  (int) $prog_data[0]["duration"];

        $class_code = $prog_data[0]["index_code"] . $completionYear;
        $class = $this->resolveClassByProgram($progID, $class_code);
        if (!$class["success"]) return $class;

        $stream_data = $this->getAppProgDetailsByAppID($appID);
        if (empty($stream_data)) return array(
            "success" => false,
            "message" => "Process terminated! Couldn't fetch applicant's stream data."
        );

        $stream = $stream_data[0]["study_stream"];
        $index_code = $prog_data[0]["index_code"];

        //check whether it's regular or weekend
        if (strtolower($stream) === "weekend") $index_code = substr($prog_data[0]["index_code"], 0, 2) . "W";

        $stdCount = $this->getTotalEnrolledApplicants($prog_data[0]["id"], $adminPeriodYear[0]["fk_academic_year"], $stream) + 1;

        if ($stdCount <= 10) $numCount = "000";
        elseif ($stdCount <= 100) $numCount = "00";
        elseif ($stdCount <= 1000) $numCount = "0";
        elseif ($stdCount <= 10000) $numCount = "";

        // Check whether it's january or august intake
        if (strtolower($term_admitted) === "august") $term = 1;
        else if (strtolower($term_admitted) === "january") $term = 2;

        $indexNumber = $index_code . $term . $numCount . $stdCount . $completionYear;

        return array(
            "success" => true,
            "message" => array(
                "index_number" => $indexNumber,
                "class" => $class_code,
                "program" => $prog_data[0]["id"],
                "department" => $prog_data[0]["department_id"],
                "stream" => $stream,
                "academic_year" => $adminPeriodYear[0]["fk_academic_year"],
                "pi" => $prog_data
            )
        );
    }

    private function createStudentEmailAddress($appID): mixed
    {
        $studentNames = $this->fetchApplicantPersInfoByAppID($appID)[0];
        $fname = $studentNames["first_name"] ? trim($studentNames["first_name"]) : $studentNames["first_name"];
        $mname = $studentNames["middle_name"] ? trim($studentNames["middle_name"]) : $studentNames["middle_name"];
        $lname = $studentNames["last_name"] ? trim($studentNames["last_name"]) : $studentNames["last_name"];

        $emailID = $fname . "." . $lname;
        $testEmailAddress = strtolower($emailID . "@st.rmu.edu.gh");
        $emailVerified = $this->verifyStudentEmailAddress($testEmailAddress);

        if (!empty($emailVerified)) {
            if (!empty($mname)) {
                $emailID = $fname . "." . $mname . "-" . $lname;
                $testEmailAddress = strtolower($emailID . "@st.rmu.edu.gh");
                $emailVerified = $this->verifyStudentEmailAddress($testEmailAddress);
                if (!empty($emailVerified)) {
                    $emailID = $fname . "." . $mname . "-" . $lname;
                    $testEmailAddress = strtolower($emailID . "@st.rmu.edu.gh");
                    $emailVerified = $this->verifyStudentEmailAddress($testEmailAddress);
                    if (!empty($emailVerified)) {
                        return array(
                            "success" => false,
                            "message" => "Failed to create a student email address! Please contact the administrator."
                        );
                    }
                }
            }
        }
        return array(
            "success" => true,
            "message" => $testEmailAddress
        );
    }

    private function verifyStudentEmailAddress($emailAddress): mixed
    {
        return $this->dm->getData(
            "SELECT `app_number` FROM `student` WHERE `email` = :e",
            array(":e" => $emailAddress)
        );
    }
    public function addNewStudent($data)
    {
        $date_admitted = date("Y-m-d");

        $query1 = "INSERT INTO `student` (`index_number`, `app_number`, `email`, `password`, `phone_number`, 
                    `prefix`, `first_name`, `middle_name`, `last_name`, `suffix`, `gender`, `dob`, `nationality`, 
                    `photo`, `marital_status`, `disability`, `date_admitted`, `term_admitted`, `stream_admitted`, 
                    `level_admitted`, `programme_duration`, `fk_academic_year`, `fk_program`, `fk_class`, `fk_department`) 
                    VALUES (:ix, :an, :ea, :pw, :pn, :px, :fn, :mn, :ln, :sx, :gd, :db, :nt, :pt, :ms, :ds, :da, :ta, :sa, :la, :pd, :fkay, :fkpg, :fkcl, :fkdt)";
        $params1 = array(
            ":ix" => $data["index_number"],
            ":an" => $data["app_number"],
            ":ea" => $data["email_generated"],
            ":pw" => $data["password"],
            ":pn" => $data["phone_no1_code"] . $data["phone_no1"],
            ":px" => ucfirst(strtolower($data["prefix"])),
            ":fn" => ucfirst(strtolower($data["first_name"])),
            ":mn" => $data["middle_name"] ? ucfirst(strtolower($data["middle_name"])) : $data["middle_name"],
            ":ln" => ucfirst(strtolower($data["last_name"])),
            ":sx" => $data["suffix"] ? ucfirst(strtolower($data["suffix"])) : $data["suffix"],
            ":gd" => $data["gender"],
            ":db" => $data["dob"],
            ":nt" => ucfirst(strtolower($data["nationality"])),
            ":pt" => $data["photo"],
            ":ms" => $data["marital_status"],
            ":ds" => $data["disability"],
            ":da" => $date_admitted,
            ":ta" => $data["term"],
            ":sa" => $data["stream"],
            ":la" => $data["level_admitted"],
            ":pd" => $data["programme_duration"],
            ":fkay" => $data["academic_year"],
            ":fkpg" => $data["program"],
            ":fkcl" => $data["class"],
            ":fkdt" => $data["department"]
        );
        $student = $this->dm->inputData($query1, $params1);
        if (empty($student)) return array("success" => false, "message" => "Failed to create a student account for applicant!");
        return array("success" => true);
    }

    private function emailApplicantEnrollmentStatus($data): mixed
    {
        //return $data;
        $pmd = match ($data["pi"][0]["code"]) {
            "BSC" => ["Bachelor of Science", "B.Sc."],
            "DIPLOMA" => ["Diploma", "Diploma"],
            "MSC" => ["Master of Science", "M.Sc."],
            "MA" => ["Master of Art", "M.A."]
        };

        $dur_word = match ($data["pi"][0]["duration"]) {
            '1' => 'One',
            '2' => 'Two',
            '3' => 'Three',
            '4' => 'Four',
            '5' => 'Five',
            '6' => 'Six',
            '7' => 'Seven',
            '8' => 'Eight',
            '9' => 'Nine',
            '10' => 'Ten'
        };

        $email = $data["email_addr"];
        $subject = "Enrollment to Regional Maritime University";
        $message = "<p>Dear " . ucfirst(strtolower($data["prefix"])) . " " . ucfirst(strtolower($data["first_name"])) . " " . ucfirst(strtolower($data["last_name"])) . ",</p>";
        $message .= "<p>You have been enrolled to Regional Maritime University to pursue ";
        $message .= "a {$dur_word} ({$data["pi"][0]["duration"]}) {$data["pi"][0]["dur_format"]} ";
        $message .= "{$pmd[0]} ({$pmd[1]}) programme in " . ucfirst(strtolower($data["pi"][0]["merit"])) . " in the Faculty of </p>";
        $message .= ucfirst(strtolower($data["pi"][0]["faculty"])) . " of the University. </p>";
        $message .= "<p><u><strong>Your student account details</strong></u></p>";
        $message .= "<div>Index Number: <strong>{$data["index_number"]}</strong></div>";
        $message .= "<div>Password: <strong>123@Password</strong> <span style='color: red'>(default)</span></div>";
        $message .= "<p>Kindly visit the <a href='https://student.rmuictonline.com'><strong>Student Portal</strong></a> to register your courses for the semester.</p>";
        $message .= "<p>Do not hesitate to contact <a href='mailto:admission@rmu.edu.gh'>admission@rmu.edu.gh</a> for any clarification.</p>";
        $message .= "<p>Congratulations on your admission to the Regional Maritime University.</p>";
        $message .= "<p>Thank you and warm regards.</p>";

        $response = $this->expose->sendEmail($email, $subject, $message);
        if (!empty($response) && is_int($response)) return 1;
        return 0;
    }

    public function smsApplicantEnrollmentStatus($data): mixed
    {
        $to = $data["phone_no1_code"] . $data["phone_no1"];
        $message = "Congratulations! Your enrollment to Regional Maritime University to pursue {$data["p"][0]["name"]} is completed. ";
        $message .= "Kindly check your mail box for more details.";
        $response = json_decode($this->expose->sendSMS($to, $message));
        if (!$response->status) return 1;
        return 0;
    }

    /**
     * @param int $appID
     * @param int $progID
     * @return mixed
     */
    public function enrollApplicant($appID, $progID, $level, $prog_dur): mixed
    {
        $term_admitted = $this->getAdmissionPeriodYearsByID($this->getCurrentAdmissionPeriodID())[0]["intake"];
        //return $term_admitted;

        //create index number from program and number of student that exists
        $index_creation_rslt = $this->createUndergradStudentIndexNumber($appID, $progID, $term_admitted);
        if (!$index_creation_rslt["success"]) return $index_creation_rslt;
        $indexCreation = $index_creation_rslt["message"];
        //return $indexCreation;

        //create email address from applicant name
        $email_generated_rslt = $this->createStudentEmailAddress($appID);
        if (!$email_generated_rslt["success"]) return $email_generated_rslt;
        $emailGenerated = $email_generated_rslt["message"];
        //return $emailGenerated;

        $appDetails = $this->getApplicantContactInfo($appID)[0];
        //return $appDetails;
        if (!$appDetails) return array("success" => false, "message" => "Failed to fetch applicant's background information!");
        //$appDetails = $app_details_rslt["message"];

        $password = password_hash("123@Password", PASSWORD_BCRYPT);
        $data = array_merge(
            [
                "email_generated" => $emailGenerated,
                "term" => $term_admitted,
                "password" => $password,
                "level_admitted" => $level,
                "programme_duration" => $prog_dur
            ],
            $indexCreation,
            $appDetails
        );
        // return $data;

        // Save Data
        $add_student_result = $this->addNewStudent($data);
        if (!$add_student_result["success"]) return $add_student_result;

        //$this->emailApplicantEnrollmentStatus($data);
        //$this->smsApplicantEnrollmentStatus($data);

        $this->updateApplicationStatus($appID, "enrolled", 1);
        return array(
            "success" => true,
            "message" => "Applicant successfully enrolled!",
            "data" => array(
                "index_number" => $index_creation_rslt["message"]["index_number"],
                "class" => $index_creation_rslt["message"]["class"],
                "program" => $index_creation_rslt["message"]["program"]
            )
        );
    }

    public function setStudentCourses($class_code, $program_id): mixed
    {
        $section = $this->dm->getData(
            "SELECT `id` FROM `section` WHERE `fk_class` = :c",
            array(":c" => $class_code)
        );

        if (!empty($section)) return array("success" => true, "message" => "Section created for this student's class [{$class_code}]!");

        $curriculum = $this->dm->getData(
            "SELECT c.`credit_hours`, c.`level`, c.`semester`, cc.`fk_course` AS course 
            FROM `curriculum` AS cc, `course` AS c 
            WHERE cc.`fk_course` = c.`code` AND cc.`fk_program` = :p",
            array(":p" => $program_id)
        );

        if (empty($curriculum)) return array("success" => false, "message" => "No curriculum found for student's program!");

        $added = 0;
        foreach ($curriculum as $curr) {
            $added += $this->dm->getData(
                "INSERT INTO `section` (`fk_class`, `fk_course`, `credit_hours`, `level`, `semester`) 
                VALUES (:fkcl, :fkcs, :cd, :lv, :sm)",
                array(
                    ":fkcl" => $class_code,
                    ":fkcs" => $curr["course"],
                    ":cd" => $curr["credit_hours"],
                    ":lv" => $curr["level"],
                    ":sm" => $curr["semester"]
                )
            );
        }

        if (!$added)  return array("success" => false, "message" => "Could not create a section for student's class [{$class_code}]!");

        return array("success" => true, "message" => "Section created for this student's class [{$class_code}]!");
    }

    public function getEnrolledApplicantDetailsByAppNum($app_number): mixed
    {
        return $this->dm->getData("SELECT * FROM `student` WHERE `app_number` = :a", array(":a" => $app_number));
    }

    /**
     * For accounts officers
     */

    // fetch dashboards stats for a vendor 
    public function fetchVendorSummary($admin_period, int $vendor_id)
    {
        $result["form-types"] = array();
        $allAvailableForms = $this->dm->getData("SELECT * FROM forms");
        foreach ($allAvailableForms as $form) {
            $form_id = $form['id'];
            $query = "SELECT ft.name, COUNT(*) AS total_num, SUM(pd.amount) AS total_amount, pd.amount AS unit_price 
                FROM purchase_detail AS pd, admission_period AS ap, forms AS ft, vendor_details AS vd 
                WHERE pd.admission_period = ap.id AND ap.id = :ai AND pd.form_id = ft.id AND pd.vendor = vd.id 
                AND pd.status = 'COMPLETED' AND ft.id = {$form_id} AND vd.id = {$vendor_id}";
            array_push($result["form-types"], $this->dm->getData($query, array(":ai" => $admin_period))[0]);
        }
        return $result;
    }

    // fetch dashboards stats
    public function fetchInitialSummaryRecord($admin_period)
    {
        $result = array();
        $result["transactions"] = [];
        $result["collections"] = [];
        $result["form-types"] = [];

        $transaction_statuses = ["TOTAL", "COMPLETED", "PENDING", "FAILED"];

        $i = 1;
        foreach ($transaction_statuses as $status) {
            if ($i == 1)
                $query = "SELECT COUNT(*) AS total FROM purchase_detail AS pd, admission_period AS ap 
                        WHERE pd.admission_period = ap.id AND ap.id = :ai";
            else
                $query = "SELECT pd.status, COUNT(*) AS total FROM purchase_detail AS pd, admission_period AS ap 
                WHERE pd.admission_period = ap.id AND ap.id = :ai AND pd.status = '{$status}'";
            array_push($result["transactions"], $this->dm->getData($query, array(":ai" => $admin_period))[0]);
            $i += 1;
        }

        $query5 = "SELECT COUNT(*) AS total_num, SUM(pd.amount) AS total_amount 
                FROM purchase_detail AS pd, admission_period AS ap 
                WHERE pd.admission_period = ap.id AND ap.id = :ai AND pd.status = 'COMPLETED'";
        $query6 = "SELECT COUNT(*) AS total_num, SUM(pd.amount) AS total_amount 
                FROM purchase_detail AS pd, admission_period AS ap, vendor_details AS vd  
                WHERE pd.admission_period = ap.id AND pd.vendor = vd.id AND vd.type <> 'ONLINE' AND ap.id = :ai AND pd.status = 'COMPLETED'";
        $query7 = "SELECT COUNT(*) AS total_num, SUM(pd.amount) AS total_amount 
                FROM purchase_detail AS pd, admission_period AS ap, vendor_details AS vd  
                WHERE pd.admission_period = ap.id AND pd.vendor = vd.id AND vd.type = 'ONLINE' AND ap.id = :ai AND pd.status = 'COMPLETED'";
        $query8 = "SELECT COUNT(*) AS total_num, SUM(pd.amount) AS total_amount 
                FROM purchase_detail AS pd, admission_period AS ap, vendor_details AS vd  
                WHERE pd.admission_period = ap.id AND pd.vendor = vd.id AND vd.type = 'ONLINE' AND ap.id = :ai AND pd.status = 'COMPLETED'";

        $result["collections"]["collect"] = $this->dm->getData($query5, array(":ai" => $admin_period))[0];
        $result["collections"]["vendor"] = $this->dm->getData($query6, array(":ai" => $admin_period))[0];
        $result["collections"]["online"] = $this->dm->getData($query7, array(":ai" => $admin_period))[0];
        $result["collections"]["provider"] = $this->dm->getData($query8, array(":ai" => $admin_period))[0];

        $allAvailableForms = $this->dm->getData("SELECT * FROM forms");
        foreach ($allAvailableForms as $form) {
            $form_id = $form['id'];
            $query9 = "SELECT ft.name, COUNT(*) AS total_num, SUM(pd.amount) AS total_amount, pd.amount AS unit_price 
                FROM purchase_detail AS pd, admission_period AS ap, forms AS ft 
                WHERE pd.admission_period = ap.id AND ap.id = :ai AND pd.form_id = ft.id 
                AND pd.status = 'COMPLETED' AND ft.id = '{$form_id}'";
            array_push($result["form-types"], $this->dm->getData($query9, array(":ai" => $admin_period))[0]);
        }

        return $result;
    }

    public function fetchAllFormPurchases($admin_period, $data = array())
    {
        $QUERY_CON = "";
        /*if (strtolower($data["admission-period"]) != "all" && !empty($data["admission-period"]))
            $QUERY_CON .= " AND pd.`admission_period` = '" . $data["admission-period"] . "'";*/
        if (!empty($data["from-date"])  && !empty($data["to-date"]))
            $QUERY_CON .= " AND DATE(pd.`added_at`) BETWEEN '" . $data["from-date"] . "'" . " AND '" . $data["to-date"] . "'";
        if (strtolower($data["form-type"]) != "all" && !empty($data["form-type"]))
            $QUERY_CON .= " AND pd.`form_id` = '" . $data["form-type"] . "'";
        if (strtolower($data["purchase-status"]) != "all" && !empty($data["purchase-status"]))
            $QUERY_CON .= " AND pd.`status` = '" . $data["purchase-status"] . "'";
        if (strtolower($data["payment-method"]) != "all" && !empty($data["payment-method"]))
            $QUERY_CON .= " AND pd.`payment_method` = '" . $data["payment-method"] . "'";

        $query = "SELECT pd.`id`, CONCAT(pd.`first_name`, ' ', pd.`last_name`) AS fullName, 
                 CONCAT('(', pd.`country_code`,') ', pd.`phone_number`) AS phoneNumber, 
                 pd.`status`, pd.`added_at`, ft.`name` AS formType, ap.`info` AS admissionPeriod, pd.`payment_method` AS paymentMethod 
                 FROM `purchase_detail` AS pd, `admission_period` AS ap, `forms` AS ft, vendor_details AS vd 
                 WHERE pd.admission_period = ap.`id` AND ap.`id` = $admin_period AND pd.form_id = ft.id AND pd.vendor = vd.`id`$QUERY_CON ORDER BY pd.`added_at` DESC";

        $_SESSION["downloadQueryStmt"] = array("type" => "dailyReport", "data" => $data, "query" => $query);
        return $this->dm->getData($query);
    }

    //
    public function fetchAllVendorFormPurchases($admin_period, $data = array())
    {
        $QUERY_CON = "";
        /*if (strtolower($data["admission-period"]) != "all" && !empty($data["admission-period"]))
            $QUERY_CON .= " AND pd.`admission_period` = '" . $data["admission-period"] . "'";*/
        if (!empty($data["from-date"])  && !empty($data["to-date"]))
            $QUERY_CON .= " AND DATE(pd.`added_at`) BETWEEN '" . $data["from-date"] . "'" . " AND '" . $data["to-date"] . "'";
        if (strtolower($data["form-type"]) != "all" && !empty($data["form-type"]))
            $QUERY_CON .= " AND pd.`form_id` = '" . $data["form-type"] . "'";
        if (strtolower($data["purchase-status"]) != "all" && !empty($data["purchase-status"]))
            $QUERY_CON .= " AND pd.`status` = '" . $data["purchase-status"] . "'";
        if (!empty($data["vendor-id"]))
            $QUERY_CON .= " AND pd.`vendor` = '" . $data["vendor-id"] . "'";

        $query = "SELECT pd.`id`, CONCAT(pd.`first_name`, ' ', pd.`last_name`) AS fullName, 
                 CONCAT('(', pd.`country_code`,') ', pd.`phone_number`) AS phoneNumber, 
                 pd.`status`, pd.`added_at`, ft.`name` AS formType, ap.`info` AS admissionPeriod 
                 FROM `purchase_detail` AS pd, `admission_period` AS ap, `forms` AS ft, vendor_details AS vd 
                 WHERE pd.admission_period = ap.`id` AND ap.`id` = $admin_period AND pd.form_id = ft.id AND pd.vendor = vd.`id`$QUERY_CON ORDER BY pd.`added_at` DESC";

        $_SESSION["downloadQueryStmt"] = array("type" => "dailyReport", "data" => $data, "query" => $query);
        return $this->dm->getData($query);
    }

    public function fetchFormPurchaseDetailsByTranID(int $transID)
    {
        $query = "SELECT pd.`id` AS transID, CONCAT(pd.`first_name`, ' ', pd.`last_name`) AS fullName, 
                pd.`email_address` AS email,  CONCAT('(', pd.`country_code`,') ', pd.`phone_number`) AS phoneN, 
                pd.`country_name` AS country, CONCAT('RMU-', pd.`app_number`) AS appN, pd.`pin_number` AS pin, 
                pd.`status`, pd.`added_at`, ft.`name` AS formT, pd.`payment_method` AS payM, 
                vd.`company` AS vendor, ap.`info` AS admisP 
                FROM `purchase_detail` AS pd, `admission_period` AS ap, `forms` AS ft, vendor_details AS vd 
                WHERE pd.`admission_period` = ap.`id` AND pd.`form_id` = ft.`id` AND pd.`vendor` = vd.`id` AND pd.`id` = :ti";
        return $this->dm->getData($query, array(":ti" => $transID));
    }

    // Create new applicants data

    private function registerApplicantPersI($user_id)
    {
        $sql = "INSERT INTO `personal_information` (`app_login`) VALUES(:a)";
        $this->dm->inputData($sql, array(':a' => $user_id));
    }

    private function registerApplicantProgI($user_id)
    {
        $sql = "INSERT INTO `program_info` (`app_login`) VALUES(:a)";
        $this->dm->inputData($sql, array(':a' => $user_id));
    }

    private function registerApplicantPreUni($user_id)
    {
        $sql = "INSERT INTO `previous_uni_records` (`app_login`) VALUES(:a)";
        $this->dm->inputData($sql, array(':a' => $user_id));
    }

    private function setFormSectionsChecks($user_id)
    {
        $sql = "INSERT INTO `form_sections_chek` (`app_login`) VALUES(:a)";
        $this->dm->inputData($sql, array(':a' => $user_id));
    }

    private function setHeardAboutUs($user_id)
    {
        $sql = "INSERT INTO `heard_about_us` (`app_login`) VALUES(:a)";
        $this->dm->inputData($sql, array(':a' => $user_id));
    }

    private function getApplicantLoginID($app_number)
    {
        $sql = "SELECT `id` FROM `applicants_login` WHERE `app_number` = :a;";
        return $this->dm->getID($sql, array(':a' => sha1($app_number)));
    }

    private function saveLoginDetails($app_number, $pin, $who)
    {
        $hashed_pin = password_hash($pin, PASSWORD_DEFAULT);
        $sql = "INSERT INTO `applicants_login` (`app_number`, `pin`, `purchase_id`) VALUES(:a, :p, :b)";
        $params = array(':a' => sha1($app_number), ':p' => $hashed_pin, ':b' => $who);

        if ($this->dm->inputData($sql, $params)) {
            $user_id = $this->getApplicantLoginID($app_number);

            //register in Personal information table in db
            $this->registerApplicantPersI($user_id);

            //register in Programs information
            $this->registerApplicantProgI($user_id);

            //register in Previous university information
            $this->registerApplicantPreUni($user_id);

            //Set initial form checks
            $this->setFormSectionsChecks($user_id);

            //Set initial form checks
            $this->setHeardAboutUs($user_id);

            return 1;
        }
        return 0;
    }

    // Proccesses to generate and send a new applicant login details
    private function genPin(int $length_pin = 9)
    {
        $str_result = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        return substr(str_shuffle($str_result), 0, $length_pin);
    }

    private function genAppNumber(int $type, int $year)
    {
        $user_code = $this->expose->genCode(5);
        $app_number = ($type * 10000000) + ($year * 100000) + $user_code;
        return $app_number;
    }

    private function doesCodeExists($code)
    {
        $sql = "SELECT `id` FROM `applicants_login` WHERE `app_number`=:p";
        if ($this->dm->getID($sql, array(':p' => sha1($code)))) {
            return 1;
        }
        return 0;
    }

    private function getAppPurchaseData(int $trans_id)
    {
        $sql = "SELECT pd.`form_id`, pd.`country_code`, pd.`phone_number`, pd.`email_address` 
                FROM `purchase_detail` AS pd, forms AS f WHERE pd.`id` = :t AND f.`id` = pd.`form_id`";
        return $this->dm->getData($sql, array(':t' => $trans_id));
    }

    private function genLoginDetails(int $type, int $year)
    {
        $rslt = 1;
        while ($rslt) {
            $app_num = $this->genAppNumber($type, $year);
            $rslt = $this->doesCodeExists($app_num);
        }
        $pin = strtoupper($this->genPin());
        return array('app_number' => $app_num, 'pin_number' => $pin);
    }

    private function updateVendorPurchaseData(int $trans_id, int $app_number, $pin_number, $status)
    {
        $sql = "UPDATE `purchase_detail` SET `app_number`= :a,`pin_number`= :p, `status` = :s WHERE `id` = :t";
        return $this->dm->getData($sql, array(':a' => $app_number, ':p' => $pin_number, ':s' => $status, ':t' => $trans_id));
    }

    private function genLoginsAndSend(int $trans_id)
    {
        $data = $this->getAppPurchaseData($trans_id);

        if (empty($data)) return array("success" => false, "message" => "No data records for this transaction!");

        $app_type = 0;

        if ($data[0]["form_id"] >= 2) {
            $app_type = 1;
        } else if ($data[0]["form_id"] == 1) {
            $app_type = 2;
        }

        $app_year = $this->expose->getAdminYearCode();

        $login_details = $this->genLoginDetails($app_type, $app_year);
        if ($this->updateVendorPurchaseData($trans_id, $login_details['app_number'], $login_details['pin_number'], 'COMPLETED'))
            return array("success" => true, "message" => $login_details);
        return array("success" => false, "message" => "Failed to update purchase records!");
    }

    /**
     * Generates and sends new applicant login details
     * @param transID - transaction id of purchase 
     */
    public function sendPurchaseInfo(int $transID, $genrateNewLoginDetails = true)
    {
        if ($genrateNewLoginDetails) {
            //generate new login details
            $gen = $this->genLoginsAndSend($transID);
            if (!$gen["success"]) return $gen;
            $data = $this->dm->getData("SELECT id FROM applicants_login WHERE purchase_id = :pi", array(":pi" => $transID));
            if (empty($data)) $this->saveLoginDetails($gen["message"]['app_number'], $gen["message"]['pin_number'], $transID);
        }

        // Get purchase data
        $data = $this->dm->getData("SELECT * FROM purchase_detail WHERE id = :ti", array(":ti" => $transID));
        if (empty($data)) return array("success" => false, "message" => "No data foound for this transaction!");

        // Prepare SMS message
        $message = 'Your RMU Online Application login details. ';
        $message .= 'APPLICATION NUMBER: RMU-' . $data[0]['app_number'];
        $message .= '    PIN: ' . $data[0]['pin_number'] . ".";
        $message .= ' Follow the link, https://admissions.rmuictonline.com start application process.';

        if ($data[0]["payment_method"] == "USSD")
            $to = "+" . $data[0]["last_name"];
        else
            $to = $data[0]["country_code"] . $data[0]["phone_number"];

        $sentEmail = false;
        $smsSent = false;

        // Send SMS message
        $response = json_decode($this->expose->sendSMS($to, $message));

        // Set SMS response status
        if (!$response->status) $smsSent = true;

        // Check if email address was provided
        if (!empty($data[0]["email_address"])) {

            // Prepare email message
            $e_message = '<p>Hi ' . $data[0]["first_name"] . ",</p>";
            $e_message .= '<p>Your RMU Online Application login details. </p>';
            $e_message .= '<p>APPLICATION NUMBER: RMU-' . $data[0]['app_number'] . '</p>';
            $e_message .= '<p>PIN: ' . $data[0]['pin_number'] . "</p>";
            $e_message .= '<p>Follow the link, https://admissions.rmuictonline.com to start application process.</p>';

            // Send email message
            $e_response = $this->expose->sendEmail($data[0]["email_address"], 'ONLINE APPLICATION PORTAL LOGIN INFORMATION', $e_message);

            // Ste email reponse status
            if ($e_response) $sentEmail = true;
        }

        // Set output message
        $output = "";
        if ($smsSent && $sentEmail) $output = "Successfully sent purchase details via SMS and email!";
        else $output = "Successfully sent purchase details!" . $to;

        // Log activity
        $this->logActivity(
            $_SESSION["user"],
            "INSERT",
            "Account user {$_SESSION["user"]} sent purchase details with transaction ID {$transID}"
        );

        // return output message
        return array("success" => true, "message" => $output);
    }

    public function verifyTransactionStatusFromDB($trans_id)
    {
        $sql = "SELECT `id`, `status` FROM `purchase_detail` WHERE `id` = :t";
        $data = $this->dm->getData($sql, array(':t' => $trans_id));

        if (empty($data)) return array("success" => false, "message" => "Invalid transaction ID! Code: -1");
        if (strtoupper($data[0]["status"]) == "FAILED") return array("success" => false, "message" => "FAILED");
        if (strtoupper($data[0]["status"]) == "COMPLETED") return array("success" => true, "message" => "COMPLETED");
        if (strtoupper($data[0]["status"]) == "PENDING") return array("success" => true, "message" => "PENDING");
    }

    public function verifyTransactionStatus($payMethod, $transID)
    {
        if ($payMethod == "CASH") return $this->verifyTransactionStatusFromDB($transID);
        else return $this->pay->verifyTransactionStatusFromOrchard($transID);
    }

    public function prepareDownloadQuery($data)
    {
        $QUERY_CON = "";
        if (strtolower($data["admission-period"]) != "all" && !empty($data["admission-period"]))
            $QUERY_CON .= " AND pd.`admission_period` = '" . $data["admission-period"] . "'";
        if (!empty($data["from-date"])  && !empty($data["to-date"]))
            $QUERY_CON .= " AND DATE(pd.`added_at`) BETWEEN '" . $data["from-date"] . "'" . " AND '" . $data["to-date"] . "'";
        if (strtolower($data["form-type"]) != "all" && !empty($data["form-type"]))
            $QUERY_CON .= " AND pd.`form_id` = '" . $data["form-type"] . "'";
        if (strtolower($data["purchase-status"]) != "all" && !empty($data["purchase-status"]))
            $QUERY_CON .= " AND pd.`status` = '" . $data["purchase-status"] . "'";
        if (strtolower($data["payment-method"]) != "all" && !empty($data["payment-method"]))
            $QUERY_CON .= " AND pd.`payment_method` = '" . $data["payment-method"] . "'";

        $_SESSION["downloadQuery"] = "SELECT pd.`id`, CONCAT(pd.`first_name`, ' ', pd.`last_name`) AS fullName, 
                 CONCAT('(', pd.`country_code`,') ', pd.`phone_number`) AS phoneNumber, 
                 pd.`status`, pd.`added_at`, ft.`name` AS formType, ap.`info` AS admissionPeriod, pd.`payment_method` AS paymentMethod 
                 FROM `purchase_detail` AS pd, `admission_period` AS ap, `forms` AS ft, vendor_details AS vd 
                 WHERE pd.admission_period = ap.`id` AND pd.form_id = ft.id AND pd.vendor = vd.`id`$QUERY_CON";
        if (isset($_SESSION["downloadQuery"]) && !empty($_SESSION["downloadQuery"])) return 1;
        return 0;
    }

    public function executeDownloadQuery()
    {
        return $this->dm->getData($_SESSION["downloadQuery"]);
    }

    public function fetchFormPurchasesGroupReport($data)
    {
        $query = "";
        $in_query = "";
        if (!empty($data["to-date"]) && !empty($data["from-date"])) $in_query .= " AND DATE(pd.`added_at`) BETWEEN '" . $data["from-date"] . "' AND '" . $data["to-date"] . "'";
        if ($data["report-by"] == "PayMethod") {
            $query = "SELECT pm.id, pd.payment_method AS title, COUNT(pd.payment_method) AS total_num_sold, SUM(pd.amount) AS total_amount_sold
                    FROM purchase_detail AS pd, vendor_details AS vd, admission_period AS ap, forms AS ft, payment_method AS pm   
                    WHERE pd.admission_period = ap.id AND pd.vendor = vd.id AND pd.form_id = ft.id AND pd.payment_method = pm.name 
                    AND pd.`status` = 'COMPLETED'$in_query GROUP BY pd.payment_method";
        }
        if ($data["report-by"] == "Vendors") {
            $query = "SELECT vd.id, vd.company AS title, COUNT(pd.vendor) AS total_num_sold, SUM(pd.amount) AS total_amount_sold
                    FROM purchase_detail AS pd, vendor_details AS vd, admission_period AS ap, forms AS ft, payment_method AS pm 
                    WHERE pd.admission_period = ap.id AND pd.vendor = vd.id AND pd.form_id = ft.id AND pd.payment_method = pm.name 
                    AND pd.`status` = 'COMPLETED'$in_query GROUP BY pd.vendor";
        }
        $_SESSION["downloadQueryStmt"] = array("type" => "groupReport", "data" => $data, "query" => $query);
        return $this->dm->getData($query);
    }

    public function fetchFormPurchasesGroupReportInfo($data)
    {
        $query = "";
        $in_query = "";
        if (!empty($data["to-date"]) && !empty($data["from-date"])) $in_query .= " AND DATE(pd.`added_at`) BETWEEN '" . $data["from-date"] . "' AND '" . $data["to-date"] . "'";

        if ($data["report-by"] == "PayMethod") {
            $query = "SELECT * FROM purchase_detail AS pd, payment_method AS pm 
                    WHERE pd.payment_method = pm.name AND pm.id = {$data["_dataI"]} AND pd.`status` = 'COMPLETED'$in_query";
        }
        if ($data["report-by"] == "Vendors") {
            $query = "SELECT * FROM purchase_detail AS pd, vendor_details AS vd 
                    WHERE pd.vendor = vd.id AND vd.id = {$data["_dataI"]} AND pd.`status` = 'COMPLETED'$in_query";
        }
        $_SESSION["downloadQueryStmt"] = array("type" => "groupReportInfo", "data" => $data, "query" => $query);
        return $this->dm->getData($query);
    }

    public function executeDownloadQueryStmt()
    {
        return $this->dm->getData($_SESSION["downloadQueryStmt"]["query"]);
    }

    // Excel Sheet Download for Admissions
    public function exportAdmissionData($status, $query)
    {
        $in_query = "";
        if ($status == "apps-completed") $in_query = "AND fsc.declaration = 1";
        if ($status == "apps-admitted") $in_query = "AND fsc.admitted = 1";
        if ($status == "apps-declined") $in_query = "AND fsc.declined = 1";
        if ($status == "apps-declined") $in_query = "AND fsc.declaration = 1";
        $sql = $query . " " . $in_query;
        return $this->dm->getData($sql);
    }

    public function fetchApplicationStatus($appID)
    {
        $query = "SELECT `declaration`, `reviewed`, `shortlisted`, `enrolled`, `admitted`, `declined`, `printed`, `programme_awarded`, `programme_duration` , `level_admitted` 
                    FROM `form_sections_chek` WHERE `app_login` = :i";
        return $this->dm->getData($query, array(":i" => $appID));
    }

    public function downloadFile($file_url)
    {
        header('Content-Type:application/octet-stream');
        header("Content-Transfer-Encoding:utf-8");
        header("Content-disposition:attachment;filename=\"" . basename($file_url) . "\"");
        readfile($file_url);
    }

    public function verifyInternationalApplicantRefNumber(string $ref_number)
    {
        preg_match('/RMUF(\d)/', $ref_number, $matches);
        if (isset($matches[1])) {
            $query = "SELECT ffp.*, f.`name`, f.`member_amount` AS amount, f.`dollar_cedis_rate` AS rate, ap.`info` FROM `foreign_form_purchase_requests` AS ffp, forms AS f, admission_period AS ap 
            WHERE ffp.`reference_number` = :r AND f.id = ffp.form AND ap.id = ffp.admission_period";
        } else {
            $query = "SELECT ffp.*, f.`name`, f.`non_member_amount` AS amount, f.`dollar_cedis_rate` AS rate, ap.`info` FROM `foreign_form_purchase_requests` AS ffp, forms AS f, admission_period AS ap 
            WHERE ffp.`reference_number` = :r AND f.id = ffp.form AND ap.id = ffp.admission_period";
        }
        return $this->dm->getData($query, array(":r" => $ref_number));
    }

    public function updateForiegnPurchaseStatus(string $ref_number, string|null $status, string $app_number = null)
    {
        if ($status == 'approved') {
            $query = "UPDATE `foreign_form_purchase_requests` SET `app_number` = :an, `status` = 'approved' WHERE `reference_number` = :rn";
            return $this->dm->inputData($query, array(":an" => $app_number, ":rn" => $ref_number));
        } else if ($status == 'declined') {
            $query = "UPDATE `foreign_form_purchase_requests` SET `status` = 'declined' WHERE `reference_number` = :rn";
            return $this->dm->inputData($query, array(":rn" => $ref_number));
        }
    }

    public function fetchForeignAppDetailsAppNumber(string $app_number)
    {
        $query = "SELECT id FROM `purchase_detail` WHERE `app_number` = :an";
        return $this->dm->getData($query, array(":an" => $app_number));
    }

    public function fetchAllInternationalFormPurchaseRequestsByStatus(string $status)
    {
        $query = "SELECT ffp.*, f.`name` AS form_type, f.`non_member_amount` AS form_price, ap.`info` AS admission_info 
                    FROM `foreign_form_purchase_requests` AS ffp, forms AS f, admission_period AS ap 
                    WHERE f.id = ffp.form AND ap.id = ffp.admission_period AND ffp.`status` = :s ORDER BY ffp.`added_at` DESC";
        return $this->dm->getData($query, array(":s" => $status));
    }

    public function fetchTotalInternationalFormPurchaseRequestsByStatus(string $status)
    {
        $query = "SELECT COUNT(`id`) AS total FROM `foreign_form_purchase_requests` WHERE `status` = :s";
        return $this->dm->getData($query, array(":s" => $status));
    }

    public function unsubmitApplication($appID)
    {
        $query = "UPDATE `form_sections_chek` SET `declaration` = 0 WHERE `app_login` = :i";
        if ($this->dm->getData($query, array(":i" => $appID))) {

            $contactInfo = $this->getApplicantContactInfo($appID);
            // Prepare SMS message
            $message = 'Hi ' . ucfirst(strtolower($contactInfo[0]["first_name"])) . " " . ucfirst(strtolower($contactInfo[0]["last_name"])) . '. ';
            $message .= 'Your application to Regional Maritime University has been unsubmitted for you provide missing details of your application. ';
            $message .= 'Kindly visit the application portal at https://admissions.rmuictonline.com to complete your application. Thank you';
            $to = $contactInfo[0]["phone_no1_code"] . $contactInfo[0]["phone_no1"];
            $smsSent = false;

            // Send SMS message
            $response = json_decode($this->expose->sendSMS($to, $message));
            if (!$response->status) $smsSent = true;
            if ($smsSent) return array("success" => true, "message" => "Application form has been unsubmitted successfully and SMS notification sent to applicant!");
            else return array("success" => true, "message" => "Application form has been unsubmitted successfully but SMS notification failed!");
        }
        return array("success" => false, "message" => "Failed to unsubmit applicant form!");
    }

    public function fetchSettingByName($setting)
    {
        $query = "SELECT * FROM `settings` WHERE `name` = :n";
        return $this->dm->getData($query, array(":n" => $setting));
    }

    public function shortlistApplicant($appLogin, $programId, $stream, $level, $sendEmail, $sendSms)
    {
        $query = "INSERT INTO shortlisted_applications (`app_login`, `program_id`, `stream`, `level`, `send_email`, `send_sms`) 
                     VALUES (:login, :program, :stream, :level, :email, :sms)";

        $result = $this->dm->inputData($query, array(
            ':login' => $appLogin,
            ':program' => $programId,
            ':stream' => $stream,
            ':level' => $level,
            ':email' => $sendEmail ? 1 : 0,
            ':sms' => $sendSms ? 1 : 0
        ));

        if (!empty($result)) {
            $query = "UPDATE `form_sections_chek` SET `shortlisted` = 1,  `programme_awarded` = :p, `level_admitted` = :l WHERE `app_login` = :i";
            $this->dm->inputData($query, array(":i" => $appLogin, ":p" => $programId, ":l" => $level));
            return array("success" => true, "message" => "Successfully shortlisted applicant");
        }
        return array("success" => false, "message" => "Failed to shortlist applicant");
    }

    public function getShortlistedApplicationsCountByStatus($status)
    {
        $query = "SELECT COUNT(`id`) AS total FROM `shortlisted_applications` WHERE `status` = :s";
        return $this->dm->getData($query, array(":s" => $status));
    }

    // Get list of pending applications
    public function getAllShortlistedApplicationsByStatus($status)
    {
        $query = "SELECT aa.*, pi.first_name, pi.middle_name, pi.last_name, pi.gender, pg.name AS program 
                FROM shortlisted_applications AS aa, personal_information AS pi, programs AS pg, applicants_login AS al 
                WHERE aa.status = :s AND aa.app_login = al.id AND aa.program_id = pg.id AND pi.app_login = al.id 
                ORDER BY created_at DESC";
        return $this->dm->getData($query, array(":s" => $status));
    }

    public function getShortlistedApplicationsByApplogin($app_login)
    {
        $query = "SELECT * FROM `shortlisted_applications` WHERE `app_login` = :a";
        return $this->dm->getData($query, array(":a" => $app_login));
    }

    public function updateShortlistedApplicationsStatus(string $app_login, string|null $status)
    {
        $query = "UPDATE `shortlisted_applications` SET `status` = :s WHERE `app_login` = :a";
        return $this->dm->inputData($query, array(":s" => $status, ":a" => $app_login));
    }

    public function approveShortlistedApplications(array $app_logins)
    {
        $admitted = 0;
        $errors = [];
        $totalApplications = count($app_logins);

        try {
            // Start transaction if using a database
            $this->dm->beginTransaction();

            foreach ($app_logins as $app_login) {
                try {
                    // Get application data
                    $data = $this->getShortlistedApplicationsByApplogin($app_login);

                    if (empty($data)) {
                        throw new Exception("Application data not found for: $app_login");
                    }

                    $applicationData = $data[0];

                    // Validate application status to prevent double processing
                    if ($applicationData['status'] === 'approved') {
                        throw new Exception("Application already approved: $app_login");
                    }

                    // Process admission
                    $admission = $this->admitIndividualApplicant(
                        $applicationData["app_login"],
                        $applicationData["program_id"],
                        $applicationData["stream"],
                        $applicationData["level"],
                        $applicationData["send_email"],
                        $applicationData["send_sms"]
                    );

                    if (!$admission["success"]) {
                        throw new Exception($admission["message"] ?? "Failed to admit applicant: $app_login");
                    }

                    // Update application status
                    $statusUpdate = $this->updateShortlistedApplicationsStatus($app_login, 'approved');
                    if (!$statusUpdate) {
                        throw new Exception("Failed to update application status: $app_login");
                    }

                    $admitted++;
                } catch (Exception $e) {
                    $errors[] = [
                        'app_login' => $app_login,
                        'error' => $e->getMessage()
                    ];
                    // Continue with next application
                }
            }

            // Commit transaction if using a database
            $this->dm->commit();

            // Prepare response
            if (empty($errors)) {
                return [
                    "success" => true,
                    "message" => "Successfully approved $admitted out of $totalApplications applications."
                ];
            } else {
                $errorCount = count($errors);
                $successCount = $totalApplications - $errorCount;

                return [
                    "success" => $successCount > 0,
                    "message" => "Processed $successCount applications successfully. Failed to process $errorCount applications.",
                    "details" => [
                        "successful" => $successCount,
                        "failed" => $errorCount,
                        "errors" => $errors
                    ]
                ];
            }
        } catch (Exception $e) {
            // Rollback transaction if using a database
            $this->dm->rollBack();
            return [
                "success" => false,
                "message" => "A system error occurred: " . $e->getMessage()
            ];
        }
    }

    /**
     * Decline multiple shortlisted applications
     * 
     * @param array $app_logins Array of application login IDs
     * @return array Response with success status and message
     */
    public function declineShortlistedApplications(array $app_logins)
    {
        $declined = 0;
        $errors = [];
        $totalApplications = count($app_logins);

        try {
            // Start transaction
            $this->dm->beginTransaction();

            foreach ($app_logins as $app_login) {
                try {
                    // Get application data to verify it exists and check current status
                    $data = $this->getShortlistedApplicationsByApplogin($app_login);

                    if (empty($data)) {
                        throw new Exception("Application not found: $app_login");
                    }

                    $applicationData = $data[0];

                    // Check if application is already processed
                    if ($applicationData['status'] === 'declined') {
                        throw new Exception("Application already declined: $app_login");
                    }

                    if ($applicationData['status'] === 'approved') {
                        throw new Exception("Cannot decline an approved application: $app_login");
                    }

                    // Update application status
                    $result = $this->updateShortlistedApplicationsStatus($app_login, 'declined');

                    if (!$result) {
                        throw new Exception("Failed to update application status: $app_login");
                    }

                    // Optional: Send notification to applicant
                    // try {
                    //     $this->sendDeclineNotification($data[0]);
                    // } catch (Exception $e) {
                    //     // Log notification error but don't fail the decline process
                    //     error_log("Failed to send decline notification for $app_login: " . $e->getMessage());
                    // }

                    $declined++;
                } catch (Exception $e) {
                    $errors[] = [
                        'app_login' => $app_login,
                        'error' => $e->getMessage()
                    ];
                    // Continue with next application
                }
            }

            // Commit transaction
            $this->dm->commit();

            // Prepare response
            if (empty($errors)) {
                return [
                    "success" => true,
                    "message" => "Successfully declined $declined out of $totalApplications applications."
                ];
            } else {
                $errorCount = count($errors);
                $successCount = $totalApplications - $errorCount;
                return [
                    "success" => $successCount > 0,
                    "message" => "Processed $successCount applications successfully. Failed to decline $errorCount applications.",
                    "details" => [
                        "successful" => $successCount,
                        "failed" => $errorCount,
                        "errors" => $errors
                    ]
                ];
            }
        } catch (Exception $e) {
            $this->dm->rollBack();
            return [
                "success" => false,
                "message" => "A system error occurred: " . $e->getMessage()
            ];
        }
    }

    // Get list of pending applications
    public function getAcceptedAdmissions($status = 0)
    {
        $query = "SELECT ar.*, fs.stream_admitted AS stream, fs.level_admitted AS level, pi.first_name, pi.middle_name, pi.last_name, pi.gender, pg.name AS program 
                FROM acceptance_receipts AS ar, personal_information AS pi, programs AS pg, applicants_login AS al, form_sections_chek AS fs 
                WHERE ar.status = :s AND 
                ar.app_login = al.id AND pi.app_login = al.id AND fs.app_login = al.id AND fs.programme_awarded = pg.id 
                ORDER BY created_at DESC";
        return $this->dm->getData($query, array(":s" => $status));
    }

    public function getAcceptedAdmissionsCountByStatus($status = 0)
    {
        $query = "SELECT COUNT(`id`) AS total FROM `acceptance_receipts` WHERE `status` = :s";
        return $this->dm->getData($query, array(":s" => $status));
    }
}
