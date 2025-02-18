<?php

namespace Src\Controller;

use Src\Base\AdmissionPeriod;
use Src\Base\Log;
use Src\Base\Program;
use Src\Controller\ExposeDataController;
use Src\System\DatabaseMethods;

class EnrollApplicant
{

    private $dm = null;
    private $expose = null;
    private $log = null;
    private $period = null;
    private $program = null;

    public function __construct($db, $user, $pass)
    {
        $this->dm = new DatabaseMethods($db, $user, $pass);
        $this->expose = new ExposeDataController($db, $user, $pass);
        $this->log = new Log($db, $user, $pass);
        $this->period = new AdmissionPeriod($db, $user, $pass);
        $this->program = new Program($db, $user, $pass);
    }

    public function enrollApplicant($appID, $progID): mixed
    {
        //create index number from program and number of student that exists
        $indexCreation = $this->createUndergradStudentIndexNumber($appID, $progID);

        //create email address from applicant name
        $emailGenerated = $this->createStudentEmailAddress($appID);

        $appDetails = $this->dm->getData(
            "SELECT * FROM `personal_information` WHERE `app_login` = :a",
            array(":a" => $appID)
        )[0];

        $term_admitted = $this->period->getAcademicPeriod($this->period->fetchCurrentAdmissionPeriod())["intake"];

        // Save Data

        $query = "INSERT INTO enrolled_applicants VALUES(`application_number`, `index_number`, `email_address`, `programme`, `first_name`, `middle_name`, `last_name`, `sex`, `dob`, `nationality`, `phone_number`, `term_admitted`, `stream_admitted`)";
        $params = array(
            $appID,
            $indexCreation["index_number"],
            $emailGenerated,
            $indexCreation["programme"],
            $appDetails["first_name"],
            $appDetails["middle_name"],
            $appDetails["last_name"],
            $appDetails["gender"],
            $appDetails["dob"],
            $appDetails["nationality"],
            $appDetails["phone_no1_code"] . $appDetails["phone_no1"],
            $term_admitted,
            $indexCreation["stream"]
        );

        $addStudent = $this->dm->inputData($query, $params)();

        if (empty($addStudent)) return array("success" => false, "message" => "Failed to enroll applicant!");
        if ($this->updateApplicationStatus($appID, "enrolled", 1))
            return array("success" => true, "message" => "Applicant successfully enrolled!");
    }

    function addStudent($data = array()): mixed
    {

        $DB_STUDENT_DATABASE = "rmu_student";
        $DB_STUDENT_USERNAME = "root";
        $DB_STUDENT_PASSWORD = "";

        $new_db = new DatabaseMethods($DB_STUDENT_DATABASE, $DB_STUDENT_USERNAME, $DB_STUDENT_PASSWORD);

        $query = "INSERT INTO enrolled_applicants VALUES(`application_number`, `index_number`, `email_address`, `programme`, `first_name`, `middle_name`, `last_name`, `sex`, `dob`, `nationality`, `phone_number`, `term_admitted`, `stream_admitted`)";
        $params = array(
            $appID,
            $indexCreation["index_number"],
            $emailGenerated,
            $indexCreation["programme"],
            $appDetails["first_name"],
            $appDetails["middle_name"],
            $appDetails["last_name"],
            $appDetails["gender"],
            $appDetails["dob"],
            $appDetails["nationality"],
            $appDetails["phone_no1_code"] . $appDetails["phone_no1"],
            $term_admitted,
            $indexCreation["stream"]
        );

        $addStudent = $new_db->inputData($query, $params)();

        if (empty($addStudent)) return array("success" => false, "message" => "Failed to enroll applicant!");
        if ($this->updateApplicationStatus($appID, "enrolled", 1))
            return array("success" => true, "message" => "Applicant successfully enrolled!");
    }

    private function createUndergradStudentIndexNumber($appID, $progID): mixed
    {
        $progInfo = $this->program->fetchAllFromProgramByID($progID)[0];

        $adminPeriodYear = $this->dm->getData(
            "SELECT YEAR(`start_date`) AS sYear FROM admission_period WHERE id = :i",
            array(":i" => $_SESSION["admin_period"])
        )[0]["sYear"];

        $startYear = (int) substr($adminPeriodYear, -2);

        $stdCount = $this->dm->getData(
            "SELECT COUNT(programme) AS total FROM enrolled_applicants WHERE programme = :p",
            array(":p" => $progInfo["name"])
        )[0]["total"] + 1;

        if ($stdCount <= 10) $numCount = "0000";
        elseif ($stdCount <= 100) $numCount = "000";
        elseif ($stdCount <= 1000) $numCount = "00";
        elseif ($stdCount <= 10000) $numCount = "0";
        elseif ($stdCount <= 100000) $numCount = "";

        if ($progInfo["dur_format"] == "year") $completionYear = $startYear +  (int) $progInfo["duration"];

        $index_code = $progInfo["index_code"];

        //check whether it's regular or weekend
        $stream_admitted = "REGULAR";
        $wkdReg = $this->getAppProgDetailsByAppID($appID)["study_stream"];
        if (!empty($wkdReg) && strtolower($wkdReg) == "weekend") {
            $wkdAvail = $this->program->fetchAllFromProgramByID($progID)["weekend"];
            if ($wkdAvail == 1) {
                $index_code = substr($progInfo["index_code"], 2) . "W";
                $stream_admitted = "WEEKEND";
            }
        }

        $indexNumber = $index_code . $numCount . $stdCount . $completionYear;
        return array("index_number" => $indexNumber, "programme" => $progInfo["name"], "stream" => $stream_admitted);
    }

    private function createStudentEmailAddress($appID): mixed
    {
        $studentNames = $this->fetchApplicantPersInfoByAppID($appID)[0];
        $emailID = $studentNames["first_name"] . "." . $studentNames["last_name"];
        $testEmailAddress = $emailID . "@st.rmu.edu.gh";
        $emailVerified = $this->verifyStudentEmailAddress($testEmailAddress);

        if (!empty($emailVerified)) {
            if (!empty($studentNames["middle_name"])) {
                $emailID = $studentNames["first_name"] . "." . $studentNames["middle_name"] . "-" . $studentNames["last_name"];
                $testEmailAddress = $emailID . "@st.rmu.edu.gh";
                $emailVerified = $this->verifyStudentEmailAddress($testEmailAddress);
                if (!empty($emailVerified)) {
                    $emailID = $studentNames["first_name"] . "." . $studentNames["middle_name"] . "-" . $studentNames["last_name"];
                    $testEmailAddress = $emailID . "@st.rmu.edu.gh";
                    $emailVerified = $this->verifyStudentEmailAddress($testEmailAddress);
                }
            }
        }
        return $emailID;
    }

    private function verifyStudentEmailAddress($emailAddress): mixed
    {
        return $this->dm->getData("SELECT `application_number` FROM `enrolled_applicants` WHERE `email_address` = :e");
    }

    public function updateApplicationStatus($appID, $statusName, $statusState)
    {
        $query = "UPDATE `form_sections_chek` SET `$statusName` = :ss WHERE `app_login` = :i";
        return $this->dm->inputData($query, array(":i" => $appID, ":ss" => $statusState));
    }

    private function getAppProgDetailsByAppID($appID)
    {
        $sql = "SELECT * FROM `program_info` WHERE `app_login` = :i";
        return $this->dm->getData($sql, array(':i' => $appID));
    }

    public function fetchApplicantPersInfoByAppID($appID): mixed
    {
        return $this->dm->getData("SELECT * FROM `personal_information` WHERE app_login = :i", array(":i" => $appID));
    }
}
