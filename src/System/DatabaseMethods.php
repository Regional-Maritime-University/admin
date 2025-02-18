<?php

namespace Src\System;

use Exception;
use PDOException;
use Src\System\DatabaseConnector;

class DatabaseMethods
{
    private $conn = null;
    private $inTransaction = false;
    private $logFile = "database_errors.log";

    public function __construct($db, $user, $pass)
    {
        $this->conn = (new DatabaseConnector($db, $user, $pass))->connect();
    }

    // New transaction methods
    public function beginTransaction()
    {
        if (!$this->inTransaction) {
            try {
                $this->inTransaction = $this->conn->beginTransaction();
                return $this->inTransaction;
            } catch (PDOException $e) {
                $this->logError($e);
                return json_encode(array("error" => "Transaction start failed: " . $e->getMessage()));
            }
        }
        return false;
    }

    public function commit()
    {
        if ($this->inTransaction) {
            try {
                $this->conn->commit();
                $this->inTransaction = false;
                return true;
            } catch (PDOException $e) {
                $this->logError($e);
                return json_encode(array("error" => "Transaction commit failed: " . $e->getMessage()));
            }
        }
        return false;
    }

    public function rollback()
    {
        if ($this->inTransaction) {
            try {
                $this->conn->rollBack();
                $this->inTransaction = false;
                return true;
            } catch (PDOException $e) {
                $this->logError($e);
                return json_encode(array("error" => "Transaction rollback failed: " . $e->getMessage()));
            }
        }
        return false;
    }

    private function query($str, $params = array())
    {
        try {
            $stmt = $this->conn->prepare($str);
            if (empty($params) || !is_array($params)) $stmt->execute();
            else $stmt->execute($params);

            if (explode(' ', $str)[0] == 'SELECT' || explode(' ', $str)[0] == 'CALL') {
                return $stmt->fetchAll();
            } elseif (explode(' ', $str)[0] == 'INSERT' || explode(' ', $str)[0] == 'UPDATE' || explode(' ', $str)[0] == 'DELETE') {
                return 1;
            }
        } catch (PDOException $e) {
            $this->logError($e);
            if ($this->inTransaction) {
                $this->rollback();
            }
            return json_encode(array("error" => $e->getMessage()));
        }
    }

    final public function getID($str, $params = array())
    {
        try {
            $result = $this->query($str, $params);
            if (!empty($result)) return $result[0]["id"];
            return 0;
        } catch (Exception $e) {
            $this->logError($e);
            if ($this->inTransaction) {
                $this->rollback();
            }
            return json_encode(array("error" => $e->getMessage()));
        }
    }

    final public function getData($str, $params = array())
    {
        try {
            $result = $this->query($str, $params);
            if (!empty($result)) return $result;
            return 0;
        } catch (Exception $e) {
            $this->logError($e);
            if ($this->inTransaction) {
                $this->rollback();
            }
            return json_encode(array("error" => $e->getMessage()));
        }
    }

    final public function inputData($str, $params = array())
    {
        try {
            $result = $this->query($str, $params);
            if (!empty($result)) return $result;
            return 0;
        } catch (Exception $e) {
            $this->logError($e);
            if ($this->inTransaction) {
                $this->rollback();
            }
            return json_encode(array("error" => $e->getMessage()));
        }
    }

    private function logError(PDOException $e)
    {
        $logFilePath = dirname(__FILE__) . '/' . $this->logFile;
        if (!file_exists($logFilePath)) {
            touch($logFilePath);
        }
        error_log("Warning: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString() . "\n", 3, $logFilePath);
    }
}
