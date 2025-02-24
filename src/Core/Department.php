<?php

namespace Src\Core;

use Src\Base\Log;
use Src\System\DatabaseMethods;

class Department
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
                $concat_stmt = "AND d.`id` = :v";
                break;

            case 'code':
                $concat_stmt = "AND d.`code` = :v";
                break;

            case 'name':
                $concat_stmt = "AND d.`name` = :v";
                break;

            default:
                $concat_stmt = "";
                break;
        }

        $query = "SELECT d.`id`, d.`name`, d.`hod` AS hod_id, 
                CONCAT(s.prefix, ' ', s.`first_name`, ' ', s.`last_name`) AS hod_name, d.`archived` 
                FROM `department` AS d, `staff` AS s 
                WHERE d.id = s.`fk_department` AND s.`role` = 'hod' AND d.`archived` = :ar $concat_stmt";
        $params = $value ? array(":v" => $value, ":ar" => $archived) : array(":ar" => $archived);
        return $this->dm->getData($query, $params);
    }

    public function add(array $data)
    {
        $query = "INSERT INTO `department`(`name`, `hod`, `archived`) VALUES(:n, :h, :ar)";
        $params = array(":n" => $data["name"], ":h" => $data["hod"], ":ar" => 0);
        $query_result = $this->dm->inputData($query, $params);
        if ($query_result) {
            $this->log->activity($_SESSION["user"], "INSERT", "Added new department {$data["name"]}");
            return array("success" => true, "message" => "New department successfully added!");
        }
        return array("success" => false, "message" => "Failed to add new department!");
    }

    public function update(array $data)
    {
        $query = "UPDATE `department` SET `name`=:n, `hod`=:h, `archived`=:ar WHERE id = :i";
        $params = array(":n" => $data["name"], ":h" => $data["hod"], ":ar" => $data["archived"], ":i" => $data["id"]);
        $query_result = $this->dm->inputData($query, $params);
        if ($query_result) {
            $this->log->activity($_SESSION["user"], "UPDATE", "Updated information for department {$data["id"]}");
            return array("success" => true, "message" => "Department information successfully updated!");
        }
        return array("success" => false, "message" => "Failed to update department information!");
    }

    public function archive(int $id)
    {
        $query = "UPDATE `department` SET `archived` = 1 WHERE id = :i";
        $query_result = $this->dm->inputData($query, array(":i" => $id));
        if ($query_result) {
            $this->log->activity($_SESSION["user"], "UPDATE", "Archived department {$id}");
            return array("success" => true, "message" => "Department with id {$id} successfully archived!");
        }
        return array("success" => false, "message" => "Failed to archive new department!");
    }

    public function delete(int $id)
    {
        $query = "DELETE FROM `department` WHERE `id` = :i";
        $query_result = $this->dm->inputData($query, array(":i" => $id));
        if ($query_result) {
            $this->log->activity($_SESSION["user"], "DELETE", "Deleted department {$id}");
            return array("success" => true, "message" => "Department with id {$id} successfully deleted!");
        }
        return array("success" => false, "message" => "Failed to delete new department!");
    }

    public function total(bool $archived = false)
    {
        $query = "SELECT COUNT(d.`id`) AS total FROM `department` AS d, `staff` AS s 
                WHERE d.`id` = s.`fk_department` AND d.`archived` = :ar";
        $params = array(":ar" => $archived);
        return $this->dm->getData($query, $params);
    }
}
