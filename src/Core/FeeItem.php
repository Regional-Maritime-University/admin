<?php

namespace Src\Core;

use Src\Base\Log;
use Src\System\DatabaseMethods;

class FeeItem
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
            case 'fee':
                $concat_stmt = "AND fi.`id` = :v";
                break;

            default:
                $concat_stmt = "";
                break;
        }

        $query = "SELECT fi.`id`, fi.`member_amount`, fi.`non_member_amount`, fi.`created_at`, 
                fi.`fk_fee_structure` AS fee_structure, fs.name AS fee_structure_name 
                FROM `fee_item` AS fi, `fee_structure` AS fs 
                WHERE fi.`fk_fee_structure` = fs.`id` AND fi.`archived` = :ar $concat_stmt ORDER BY fi.`id` DESC";
        $params = $value ? array(":v" => $value, ":ar" => $archived) : array(":ar" => $archived);
        return $this->dm->getData($query, $params);
    }

    public function add(array $data)
    {
        $selectQuery = "SELECT * FROM `fee_item` WHERE `fk_fee_structure` = :fs AND `name`=:n";
        $feeItemData = $this->dm->getData(
            $selectQuery,
            array(
                ":n" => $data["name"],
                ":fs" => $data["fee_structure"]
            )
        );

        if (!empty($feeItemData)) {
            return array(
                "success" => false,
                "message" => "Fee item {$feeItemData[0]["name"]} with id {$feeItemData[0]["id"]} already exist in database!"
            );
        }

        $query = "INSERT INTO `fee_item` (`fk_fee_structure`, `currency`, `name`, `member_amount`, `non_member_amount`) 
                VALUES(:p, :t, :c, :n, :m, :nm)";
        $params = array(
            ":fs" => $data["fee_structure"],
            ":n" => $data["name"],
            ":c" => $data["currency"],
            ":m" => $data["member_amount"],
            ":nm" => $data["non_member_amount"]
        );
        $query_result = $this->dm->inputData($query, $params);
        if ($query_result) {
            $this->log->activity($_SESSION["user"], "INSERT", "Added new fee item {$data["name"]}");
            return array("success" => true, "message" => "New fee item successfully added!");
        }
        return array("success" => false, "message" => "Failed to add new fee item!");
    }

    public function update(array $data)
    {
        $query = "UPDATE fee_item SET 
        `fk_fee_structure`=:fs, `currency`=:c, `name`=:n, `member_amount`:m, `non_member_amount`:nm, `archived`=:ar 
        WHERE `id` = :i";
        $params = array(
            ":i" => $data["fee_item"],
            ":fs" => $data["fee_structure"],
            ":c" => $data["currency"],
            ":n" => $data["name"],
            ":m" => $data["member_amount"],
            ":nm" => $data["non_member_amount"],
            ":ar" => 0
        );
        $query_result = $this->dm->inputData($query, $params);
        if ($query_result) {
            $this->log->activity($_SESSION["user"], "UPDATE", "Updated information for fee item {$data["fee_item"]}");
            return array("success" => true, "message" => "Fee item successfully updated!");
        }
        return array("success" => false, "message" => "Failed to update fee item!");
    }

    public function archive($id)
    {
        $query = "UPDATE fee_item SET archived = 1 WHERE `id` = :i";
        $query_result = $this->dm->inputData($query, array(":i" => $id));
        if ($query_result) {
            $this->log->activity($_SESSION["user"], "DELETE", "Archived fee item {$id}");
            return array("success" => true, "message" => "Fee item with id {$id} successfully archived!");
        }
        return array("success" => false, "message" => "Failed to add new fee item!");
    }

    public function delete($id)
    {
        $query = "DELETE FROM fee_item WHERE id = :i";
        $query_result = $this->dm->inputData($query, array(":i" => $id));
        if ($query_result) {
            $this->log->activity($_SESSION["user"], "DELETE", "Deleted fee item {$id}");
            return array("success" => true, "message" => "Fee item with code {$id} successfully deleted!");
        }
        return array("success" => false, "message" => "Failed to delete fee item!");
    }

    public function total(string $key = "", string $value = "", bool $archived = false)
    {
        $concat_stmt = "";
        switch ($key) {
            case 'type':
                $concat_stmt = "AND fi.`type` = :v";
                break;

            case 'category':
                $concat_stmt = "AND fi.`category` = :v";
                break;

            case 'name':
                $concat_stmt = "AND fi.`name` = :v";
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
