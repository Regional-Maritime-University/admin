<?php

namespace Src\Core;

use Src\Base\Log;
use Src\System\DatabaseMethods;

class Staff
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
            case 'number':
                $concat_stmt = "AND s.`number` = :v";
                break;

            case 'name':
                $concat_stmt = "AND s.`name` = :v";
                break;

            case 'role':
                $concat_stmt = "AND s.`role` = :v";
                break;

            case 'gender':
                $concat_stmt = "AND s.`gender` = :v";
                break;

            case 'department':
                $concat_stmt = "AND s.`fk_department` = :v";
                break;

            default:
                $concat_stmt = "";
                break;
        }

        $query = "SELECT s.`number`, s.`email`, s.`password`, CONCAT(s.`prefix`, ' ', s.`first_name`, ' ', s.`last_name`) AS `name`, 
                s.`gender`, s.`role`, s.`archived`, s.`fk_department` AS department_id, d.`name` AS department_name, s.`archived` 
                FROM `staff` AS s, `department` AS d WHERE s.`fk_department` = d.`id` AND s.`archived` = :ar $concat_stmt";
        $params = $value ? array(":v" => $value, ":ar" => $archived) : array(":ar" => $archived);
        return $this->dm->getData($query, $params);
    }

    public function add(array $data)
    {
        $query = "INSERT INTO `staff` (`number`, `email`, `password`, `first_name`, `middle_name`, 
                `last_name`, `prefix`, `gender`, `role`, `fk_department`, `archived`) 
                VALUES(:n, :e, :fn, :mn, :ln, :p, :g, :r, :d, :ar)";
        $params = array(
            ":n" => $data["number"],
            ":e" => $data["email"],
            ":fn" => $data["first_name"],
            ":mn" => $data["middle_name"],
            ":ln" => $data["last_name"],
            ":p" => $data["prefix"],
            ":g" => $data["gender"],
            ":r" => $data["role"],
            ":d" => $data["fk_department"],
            ":ar" => 0
        );
        $query_result = $this->dm->inputData($query, $params);
        if ($query_result)
            $this->log->activity($_SESSION["user"], "INSERT", "Added new staff {$data["name"]} of staff type {$data["type"]}");
        return $query_result;
    }

    public function update(array $data)
    {
        $query = "UPDATE `staff` SET 
        `number`=:n, `email`=:e, `password`=:fn, `first_name`=:mn, 
        `middle_name`, `last_name`=:ln, `prefix`=:p, `gender`=:g, `role`=:r, 
        `fk_department`=:d, `archived`=:ar WHERE s.`number` = :i";
        $params = array(
            ":i" => $data["c_number"],
            ":n" => $data["number"],
            ":e" => $data["email"],
            ":fn" => $data["first_name"],
            ":mn" => $data["middle_name"],
            ":ln" => $data["last_name"],
            ":p" => $data["prefix"],
            ":g" => $data["gender"],
            ":r" => $data["role"],
            ":d" => $data["fk_department"],
            ":ar" => 0
        );
        $query_result = $this->dm->inputData($query, $params);
        if ($query_result) $this->log->activity($_SESSION["user"], "UPDATE", "Updated information for staff {$data["id"]}");
        return $query_result;
    }

    public function archive($number)
    {
        $query = "UPDATE `staff` SET archived = 1 WHERE `number` = :i";
        $query_result = $this->dm->inputData($query, array(":i" => $number));
        if ($query_result) $this->log->activity($_SESSION["user"], "DELETE", "Archived staff {$number}");
        return $query_result;
    }

    public function delete($number)
    {
        $query = "DELETE FROM `staff` WHERE `number` = :i";
        $query_result = $this->dm->inputData($query, array(":i" => $number));
        if ($query_result) $this->log->activity($_SESSION["user"], "DELETE", "Deleted staff {$number}");
        return $query_result;
    }

    public function total(string $key = "", string $value = "", bool $archived = false)
    {
        $concat_stmt = "";
        switch ($key) {
            case 'role':
                $concat_stmt = "AND s.`role` = :v";
                break;

            case 'gender':
                $concat_stmt = "AND s.`gender` = :v";
                break;

            case 'department':
                $concat_stmt = "AND s.`fk_department` = :v";
                break;

            default:
                $concat_stmt = "";
                break;
        }
        $query = "SELECT COUNT(s.`number`) AS total FROM `staff` AS s, `department` AS d 
                WHERE d.`id` = s.`fk_department` AND s.`archived` = :ar $concat_stmt";
        $params = $value ? array(":v" => $value, ":ar" => $archived) : array(":ar" => $archived);
        return $this->dm->getData($query, $params);
    }
}
