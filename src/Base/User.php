<?php

namespace Src\Base;

use Src\Controller\ExposeDataController;
use Src\System\DatabaseMethods;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class User
{

    private $dm = null;
    private $expose = null;
    private $log = null;

    public function __construct($db, $user, $pass)
    {
        $this->dm = new DatabaseMethods($db, $user, $pass);
        $this->expose = new ExposeDataController($db, $user, $pass);
        $this->log = new Log($db, $user, $pass);
    }

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
            ":fn" => $user_data["first_name"], ":ln" => $user_data["last_name"], ":un" => $user_data["user_name"],
            ":pw" => $hashed_pw, ":rl" => $user_data["user_role"], ":tp" => $user_data["user_type"]
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
            ":ui" => $sys_user[0]["id"], ":s" => $privileges["select"], ":i" => $privileges["insert"],
            ":u" => $privileges["update"], ":d" => $privileges["delete"]
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
                ":id" => $vendor_id, ":tp" => "VENDOR", ":cp" => $user_data["vendor_company"],
                ":cc" => strtoupper($user_data["company_code"]), ":bh" => $user_data["vendor_branch"],
                ":vr" => $user_data["vendor_role"], ":pn" => $user_data["vendor_phone"],
                ":ui" => $sys_user[0]["id"], ":au" => $user_data["api_user"]
            );
            $this->dm->inputData($query1, $params1);
            $subject = "Regional Maritime University - Vendor Account";
        }

        $this->log->activity(
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
            ":un" => $data["user-email"], ":fn" => $data["user-fname"], ":ln" => $data["user-lname"],
            ":rl" => $data["user-role"], ":tp" => $data["user-type"], ":id" => $data["user-id"]
        );
        if ($this->dm->inputData($query, $params)) {
            // Create insert query for user privileges
            $query2 = "UPDATE `sys_users_privileges` SET `select` = :s, `insert` = :i,`update` = :u, `delete`= :d 
                        WHERE `user_id` = :ui";
            $params2 = array(
                ":ui" => $data["user-id"], ":s" => $privileges["select"], ":i" => $privileges["insert"],
                ":u" => $privileges["update"], ":d" => $privileges["delete"]
            );
            // Execute user privileges 
            $action2 = $this->dm->inputData($query2, $params2);
            if (!$action2) return array("success" => false, "message" => "Failed to update user account privileges!");

            $this->log->activity(
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

            $this->log->activity(
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
            $this->log->activity(
                $_SESSION["user"],
                "DELETE",
                "Removed user {$user_id} accounts"
            );
        return $query_result;
    }

    // CRUD for vendor

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

        $reader = new Xlsx();
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
                "first_name" => $mainBranch, "last_name" => $v_branch, "user_name" => $v_email,
                "user_role" => "Vendors", "vendor_company" => $mainBranch,
                "vendor_phone" => $v_phone, "vendor_branch" => $v_branch, "vendor_role" => $v_role
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

        $this->log->activity($_SESSION["user"], "UPDATE", "Updated information for vendor {$v_id}");
        return array("success" => true, "message" => "Successfully updated vendor's account information!");
    }

    public function deleteVendor($vendor_id)
    {
        $vendor_info = $this->fetchVendor($vendor_id);
        $this->deleteSystemUser($vendor_info[0]["user_id"]);
        if ($vendor_info[0]["api_user"] == 1) $this->dm->inputData("DELETE FROM api_users WHERE vendor_id = :i", array(":i" => $vendor_id));
        $query_result2 = $this->dm->inputData("DELETE FROM vendor_details WHERE id = :i", array(":i" => $vendor_id));

        if ($query_result2)
            $this->log->activity(
                $_SESSION["user"],
                "DELETE",
                "Deleted vendor {$vendor_id} information"
            );
        return $query_result2;
    }

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
}
