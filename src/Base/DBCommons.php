<?php

namespace Src\Base;

use Src\Controller\ExposeDataController;
use Src\System\DatabaseMethods;

class DBCommons
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

    // Admission Letter Data

    public function fetchAllAdmissionLetterData(): mixed
    {
        return $this->dm->getData("SELECT * FROM `admission_letter_data`");
    }

    public function fetchCurrentAdmissionLetterData(): mixed
    {
        return $this->dm->getData("SELECT * FROM `admission_letter_data` WHERE `in_use` = 1");
    }

    public function addAdmissionLetterData(): mixed
    {
        return $this->dm->getData("INSERT INTO `admission_letter_data` VALUES()");
    }

    public function updateAdmissionLetterData($let_id): mixed
    {
        return $this->dm->getData("UPDATE `admission_letter_data` SET WHERE `in_use` = :i");
    }

    public function activateAdmissionLetterData($let_id, $status): mixed
    {
        $current = $this->fetchCurrentAdmissionLetterData()["id"];

        $query = "UPDATE `admission_letter_data` SET `in_use` = 1 WHERE id = :i";
        $query_result = $this->dm->inputData($query, array(":i" => $let_id));
        if (empty($query_result)) return 0;

        $query = "UPDATE `admission_letter_data` SET `in_use` = 1 WHERE id = :i";
        $this->dm->inputData($query, array(":i" => $$current));

        return $query_result;
    }

    public function removeAdmissionLetterData(): mixed
    {
        return $this->dm->getData("SELECT * FROM `admission_letter_data` WHERE `in_use` = 1");
    }

    // Admission Period

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

    public function addAdmissionPeriod($adp_start, $adp_end, $adp_acad, $adp_info, $intake)
    {
        $query = "INSERT INTO admission_period (`start_date`, `end_date`, `academic_year`, `info`, `intake`) VALUES(:sd, :ed, :ay, :i, :t)";
        $params = array(":sd" => $adp_start, ":ed" => $adp_end, ":ay" => $adp_acad, ":i" => $adp_info, ":t" => $intake);
        $query_result = $this->dm->inputData($query, $params);
        if (empty($query_result)) return array("success" => false, "message" => "Failed to open new admission period!");

        $this->openOrCloseAdmissionPeriod($this->expose->getCurrentAdmissionPeriodID(), 0);
        $this->openOrCloseAdmissionPeriod($query_result, 1);

        $this->log->activity(
            $_SESSION["user"],
            "INSERT",
            "Added admisiion period  with start date {$adp_start} and end date {$adp_end}"
        );

        return array("success" => true, "message" => "New admission period successfully open!");
    }

    public function updateAdmissionPeriod($adp_id, $adp_start, $adp_end, $adp_acad, $adp_info, $intake)
    {
        $query = "UPDATE admission_period SET `start_date` = :sd, `end_date` = :ed, `academic_year` = :ay, `info` = :i, `intake` = :t 
                    WHERE id = :id";
        $params = array(":sd" => $adp_start, ":ed" => $adp_end, ":ay" => $adp_acad, ":i" => $adp_info, ":t" => $intake, ":id" => $adp_id);
        $query_result = $this->dm->inputData($query, $params);
        if (empty($query_result)) return array("success" => false, "message" => "Failed to update admission information!");

        if ($query_result)
            $this->log->activity(
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
        if ($status == 0) $this->log->activity($_SESSION["user"], "UPDATE", "Closed admission with id {$adp_id}");
        else if ($status == 1) $this->log->activity($_SESSION["user"], "UPDATE", "Opened admission with id {$adp_id}");
        return $query_result;
    }

    // Applicant

    public function fetchApplicantPersInfoByAppID(int $appID): mixed
    {
        return $this->dm->getData("SELECT * FROM `personal_information` WHERE app_login = :i", array(":i" => $appID));
    }

    public function fetchApplicantAppNumber(int $appID): mixed
    {
        return $this->dm->getData("SELECT pd.`app_number` FROM `purchase_detail` AS pd, applicants_login AS al 
        WHERE al.purchase_id = pd.id AND al.id = :i", array(":i" => $appID));
    }

    // Program

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
        return $this->dm->getData("SELECT * FROM programs WHERE `id` = :i", array(":i" => $prog_id));
    }

    public function addProgramme($prog_name, $prog_type, $prog_wkd, $prog_grp)
    {
        $query = "INSERT INTO programs (`name`, `type`, `weekend`, `group`) VALUES(:n, :t, :w, :g)";
        $params = array(":n" => strtoupper($prog_name), ":t" => $prog_type, ":w" => $prog_wkd, ":g" => $prog_grp);
        $query_result = $this->dm->inputData($query, $params);
        if ($query_result)
            $this->log->activity(
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
            $this->log->activity(
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
            $this->log->activity(
                $_SESSION["user"],
                "DELETE",
                "Deleted programme {$prog_id}"
            );
        return $query_result;
    }
}
