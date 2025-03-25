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
            case 'id':
                $concat_stmt = "AND `id` = :v";
                break;

            case 'name':
                $concat_stmt = "AND `name` = :v";
                break;

            default:
                $concat_stmt = "";
                break;
        }

        $query = "SELECT * FROM `fee_item` WHERE `archived` = :ar $concat_stmt ORDER BY `id` DESC";
        $params = $value ? array(":v" => $value, ":ar" => $archived) : array(":ar" => $archived);
        return $this->dm->getData($query, $params);
    }

    public function add(array $data)
    {
        $selectQuery = "SELECT * FROM `fee_item` WHERE `name` = :n";
        $feeItemData = $this->dm->getData($selectQuery, array(":n" => $data["name"]));

        if (!empty($feeItemData)) {
            array_push($errors, "Fee item {$feeItemData[0]["name"]} already exist in database!");
        } else {
            $query = "INSERT INTO `fee_item` (`name`, `value`) VALUES(:n, :v)";
            $params = array(":n" => $data["name"], ":v" => $data["value"]);
            $query_result = $this->dm->inputData($query, $params);
            if ($query_result) {
                $this->log->activity($_SESSION["user"], "INSERT", "Added new fee item {$data["name"]}");
            } else {
                return array("success" => "Encounter a server error while adding fee item {$data["name"]} to database!");
            }
        }
        return array("success" => true,  "message" => "fee item added!");
    }

    public function update(array $data)
    {
        $query = "UPDATE fee_item SET `name`=:n, `value`=:v WHERE `id` = :i";
        $params = array(
            ":i" => $data["fee_item"],
            ":n" => $data["name"],
            ":v" => $data["value"]
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
        $query = "UPDATE `fee_item` SET `archived` = 1 WHERE `id` = :i";
        $query_result = $this->dm->inputData($query, array(":i" => $id));
        if ($query_result) {
            $this->log->activity($_SESSION["user"], "DELETE", "Archived fee item {$id}");
            return array("success" => true, "message" => "Fee item successfully archived!");
        }
        return array("success" => false, "message" => "Failed to add new fee item!");
    }

    public function unarchive(array $items)
    {
        $unarchived = 0;
        foreach ($items as $item) {
            $query = "UPDATE `fee_item` SET `archived` = 0 WHERE `id` = :i";
            $query_result = $this->dm->inputData($query, array(":i" => $item));
            if ($query_result) {
                $this->log->activity($_SESSION["user"], "UPDATE", "Unarchived fee item {$item}");
                $unarchived += 1;
            }
        }
        return array(
            "success" => true,
            "message" => "{$unarchived} successfully unarchived!",
            "errors" => "Failed to unarchive " . (count($items) - $unarchived) . " items"
        );
    }

    public function delete(array $items)
    {
        $deleted = 0;
        foreach ($items as $item) {
            $query = "DELETE FROM `fee_item` WHERE `id` = :i";
            $query_result = $this->dm->inputData($query, array(":i" => $item));
            if ($query_result) {
                $this->log->activity($_SESSION["user"], "DELETE", "Deleted fee item {$item}");
                $deleted += 1;
            }
        }
        return array(
            "success" => true,
            "message" => "{$deleted} successfully deleted!",
            "errors" => "Failed to delete " . (count($items) - $deleted) . " items"
        );
    }

    public function total(bool $archived = false)
    {
        $query = "SELECT COUNT(*) AS total FROM `fee_item` WHERE `archived` = :ar";
        $params = array(":ar" => $archived);
        return $this->dm->getData($query, $params);
    }
}
