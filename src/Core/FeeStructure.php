<?php

namespace Src\Core;

use Src\Base\Log;
use Src\System\DatabaseMethods;

class FeeStructure
{

    private $dm = null;
    private $log = null;

    public function __construct($db, $user, $pass)
    {
        $this->dm = new DatabaseMethods($db, $user, $pass);
        $this->log = new Log($db, $user, $pass);
    }

    public function fetch(string $key = "", string $value = "", bool $archived = false)
    {
        switch ($key) {
            case 'id':
                $concat_stmt = "AND fs.`id` = :v";
                break;

            case 'type':
                $concat_stmt = "AND fs.`type` = :v";
                break;

            case 'category':
                $concat_stmt = "AND fs.`category` = :v";
                break;

            case 'name':
                $concat_stmt = "AND fs.`name` = :v";
                break;

            case 'program':
                $concat_stmt = "AND pg.`name` = :v";
                break;

            default:
                $concat_stmt = "";
                break;
        }

        $query = "SELECT fs.`id`, fs.`fk_program_id` AS program_id, fs.`type`, fs.`category`, fs.`name`, fs.`currency`, 
                fs.`member_amount`, fs.`non_member_amount`, fs.`created_at`, 
                pg.name AS program_name, pg.regular AS program_reg_available, pg.weekend AS program_wkd_available 
                FROM `fee_structure` AS fs, `programs` AS pg 
                WHERE fs.`fk_program_id` = pg.`id` AND fs.`archived` = :ar $concat_stmt ORDER BY fs.`id` DESC";
        $params = $value ? array(":v" => $value, ":ar" => $archived) : array(":ar" => $archived);
        return $this->dm->getData($query, $params);
    }

    public function add(array $data)
    {
        $selectQuery = "SELECT * FROM `fee_structure` WHERE `fk_program_id` = :p AND `type` = :t AND `category` = :c";
        $feeStructureData = $this->dm->getData(
            $selectQuery,
            array(
                ":p" => $data["program"],
                ":t" => $data["type"],
                ":c" => $data["category"]
            )
        );

        if (!empty($feeStructureData)) {
            return array(
                "success" => false,
                "message" => "Fee structure {$feeStructureData[0]["name"]} with id {$feeStructureData[0]["id"]} already exist in database!"
            );
        }

        $program = $this->dm->getData("SELECT `index_code` FROM `programs` WHERE `id` = :p", [":p" => $data["program"]]);
        $fee_structure_name = $program[0]["index_code"] . " - {$data["type"]}" . " [{$data["category"]}]";

        $query = "INSERT INTO `fee_structure` 
                (`fk_program_id`, `currency`, `type`, `category`, `name`, `member_amount`, `non_member_amount`) 
                VALUES(:p, :r, :t, :c, :n, :m, :nm)";
        $params = array(
            ":p" => $data["program"],
            ":r" => $data["currency"],
            ":t" => $data["type"],
            ":c" => $data["category"],
            ":n" => $fee_structure_name,
            ":m" => $data["member_amount"],
            ":nm" => $data["non_member_amount"]
        );
        $query_result = $this->dm->inputData($query, $params);
        if ($query_result) {
            $this->log->activity($_SESSION["user"], "INSERT", "Added new fee structure {$fee_structure_name}");
            return array("success" => true, "message" => "New fee structure successfully added!");
        }
        return array("success" => false, "message" => "Failed to add new fee structure!");
    }

    public function update(array $data)
    {
        $program = $this->dm->getData("SELECT `index_code` FROM `programs` WHERE `id` = :p", [":p" => $data["program"]]);
        $fee_structure_name = $program[0]["index_code"] . " - {$data["type"]}" . " [{$data["category"]}]";

        $query = "UPDATE fee_structure SET 
        `fk_program_id`=:p, `currency`=:c, `type`=:t, `category`=:c, `name`=:n, 
        `member_amount`:m, `non_member_amount`:nm, `archived`=:ar WHERE `id` = :i";
        $params = array(
            ":i" => $data["fee_structure"],
            ":p" => $data["program"],
            ":t" => $data["type"],
            ":r" => $data["currency"],
            ":c" => $data["category"],
            ":n" => $fee_structure_name,
            ":m" => $data["member_amount"],
            ":nm" => $data["non_member_amount"],
            ":ar" => 0
        );
        $query_result = $this->dm->inputData($query, $params);
        if ($query_result) {
            $this->log->activity($_SESSION["user"], "UPDATE", "Updated information for fee structure {$data["fee_structure"]}");
            return array("success" => true, "message" => "Fee structure successfully updated!");
        }
        return array("success" => false, "message" => "Failed to update fee structure!");
    }

    public function archive($id)
    {
        $query = "UPDATE fee_structure SET archived = 1 WHERE `id` = :i";
        $query_result = $this->dm->inputData($query, array(":i" => $id));
        if ($query_result) {
            $this->log->activity($_SESSION["user"], "DELETE", "Archived fee structure {$id}");
            return array("success" => true, "message" => "Fees structure with id {$id} successfully archived!");
        }
        return array("success" => false, "message" => "Failed to add new fee structure!");
    }

    public function delete($id)
    {
        $query = "DELETE FROM fee_structure WHERE id = :i";
        $query_result = $this->dm->inputData($query, array(":i" => $id));
        if ($query_result) {
            $this->log->activity($_SESSION["user"], "DELETE", "Deleted fee structure {$id}");
            return array("success" => true, "message" => "Fees structure with code {$id} successfully deleted!");
        }
        return array("success" => false, "message" => "Failed to delete fee structure!");
    }

    public function total(string $key = "", string $value = "", bool $archived = false)
    {
        $concat_stmt = "";
        switch ($key) {
            case 'type':
                $concat_stmt = "AND fs.`type` = :v";
                break;

            case 'category':
                $concat_stmt = "AND fs.`category` = :v";
                break;

            case 'name':
                $concat_stmt = "AND fs.`name` = :v";
                break;

            case 'program':
                $concat_stmt = "AND pg.`name` = :v";
                break;

            default:
                $concat_stmt = "";
                break;
        }
        $query = "SELECT COUNT(c.`code`) AS total FROM `course` AS c, `course_category` AS cg, `department` AS d 
        WHERE c.`fk_category` = cg.`id` AND c.`fk_department` = d.`id` AND c.`archived` = :ar $concat_stmt";
        $params = $value ? array(":v" => $value, ":ar" => $archived) : array(":ar" => $archived);
        return $this->dm->getData($query, $params);
    }
}
