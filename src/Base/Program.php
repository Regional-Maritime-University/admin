<?php

namespace Src\Base;

use Src\Controller\ExposeDataController;
use Src\System\DatabaseMethods;

class Program
{

    private $dm = null;
    private $log = null;

    public function __construct($db, $user, $pass)
    {
        $this->dm = new DatabaseMethods($db, $user, $pass);
        $this->log = new Log($db, $user, $pass);
    }

    public function fetchPrograms(string $key = "", string $value = "")
    {
        switch ($key) {
            case 'id':
                $concat_stmt = "AND p.`id` = :v";
                break;

            case 'code':
                $concat_stmt = "AND p.`code` = :v";
                break;

            case 'name':
                $concat_stmt = "AND p.`name` = :v";
                break;

            case '':
                $concat_stmt = "";
                break;

            default:
                $concat_stmt = "";
                break;
        }

        $params = $value ? array(":v" => $value) : array();

        $query = "SELECT p.`id`, p.`name`, p.`merit`, p.`department`, p.`regulation`, p.`category`, 
                p.`code`, p.`index_code`, p.`faculty`, p.`duration`, p.`dur_format`, p.`num_of_semesters`, 
                p.`type` AS type_id, f.`name` AS `type` , p.`regular`, p.`weekend`, p.`group`, p.`archived` 
                FROM `programs` AS p, `forms` AS f WHERE p.`type` = f.`id` $concat_stmt";
        return $this->dm->getData($query, $params);
    }

    public function addProgramme(array $data)
    {
        $query = "INSERT INTO programs (`name`, `merit`, `department`, `regulation`, 
                `category`, `code`, `index_code`, `faculty`, `duration`, `dur_format`, 
                `num_of_semesters`, `type`, `regular`, `weekend`, `group`, `archived`) 
                VALUES(:n, :m, :dm, :rl, :cg, :c, :ic, :f, :d, :df,  :ns,  :t,  :r,  :w, :g, :ar)";
        $params = array(
            ":n" => $data["name"],
            ":m" => $data["merit"],
            ":dm" => $data["department"],
            ":rl" => $data["regulation"],
            ":cg" => $data["category"],
            ":c" => $data["code"],
            ":ic" => $data["index_code"],
            ":f" => $data["faculty"],
            ":d" => $data["duration"],
            ":df" => $data["dur_format"],
            ":ns" => $data["num_of_semesters"],
            ":t" => $data["type"],
            ":r" => $data["regular"],
            ":w" => $data["weekend"],
            ":g" => $data["group"],
            ":ar" => 0
        );
        $query_result = $this->dm->inputData($query, $params);
        if ($query_result)
            $this->log->activity($_SESSION["user"], "INSERT", "Added new programme {$data["name"]} of programme type {$data["type"]}");
        return $query_result;
    }

    public function updateProgramme(array $data)
    {
        $query = "UPDATE programs SET 
        `name`=:n, `merit`=:m, `department`=:dm, `regulation`=:rl, 
        `category`=:cg, `code`=:c, `index_code`=:ic, `faculty`=:f, 
        `duration`=:d, `dur_format`=:df, `num_of_semesters`=:ns, 
        `type`=:t, `regular`=:r, `weekend`=:w, `group`=:g , `archived`=:ar WHERE id = :i";

        $params = array(
            ":n" => $data["name"],
            ":m" => $data["merit"],
            ":dm" => $data["department"],
            ":rl" => $data["regulation"],
            ":cg" => $data["category"],
            ":c" => $data["code"],
            ":ic" => $data["index_code"],
            ":f" => $data["faculty"],
            ":d" => $data["duration"],
            ":df" => $data["dur_format"],
            ":ns" => $data["num_of_semesters"],
            ":t" => $data["type"],
            ":r" => $data["regular"],
            ":w" => $data["weekend"],
            ":g" => $data["group"],
            ":ar" => $data["archived"]
        );
        $query_result = $this->dm->inputData($query, $params);
        if ($query_result) $this->log->activity($_SESSION["user"], "UPDATE", "Updated information for program {$data["id"]}");
        return $query_result;
    }

    public function archiveProgramme($id)
    {
        $query = "UPDATE programs SET archived = 1 WHERE id = :i";
        $query_result = $this->dm->inputData($query, array(":i" => $id));
        if ($query_result) $this->log->activity($_SESSION["user"], "DELETE", "Archived programme {$id}");
        return $query_result;
    }

    public function deleteProgramme($id)
    {
        $query = "DELETE FROM programs WHERE id = :i";
        $query_result = $this->dm->inputData($query, array(":i" => $id));
        if ($query_result) $this->log->activity($_SESSION["user"], "DELETE", "Deleted programme {$id}");
        return $query_result;
    }
}
