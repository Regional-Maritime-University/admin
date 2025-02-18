<?php

namespace Src\Base;

use Src\Controller\ExposeDataController;
use Src\System\DatabaseMethods;

class AdmissionPeriod
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

    public function fetchAllAdmissionPeriod()
    {
        return $this->dm->getData("SELECT * FROM admission_period ORDER BY `id` DESC");
    }

    public function fetchCurrentAdmissionPeriod()
    {
        return $this->dm->getData("SELECT * FROM admission_period WHERE `active` = 1");
    }

    public function fetchAdmissionPeriod($adp_id)
    {
        $query = "SELECT * FROM admission_period WHERE id = :i";
        return $this->dm->inputData($query, array(":i" => $adp_id));
    }

    public function getAcademicPeriod($admin_period)
    {
        $query = "SELECT YEAR(`start_date`) AS start_year, YEAR(`end_date`) AS end_year, `info`, `intake`, `active`, `closed` 
                FROM admission_period WHERE id = :ai";
        return $this->dm->getData($query, array(":ai" => $admin_period));
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
        $this->log->activity(
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
}
