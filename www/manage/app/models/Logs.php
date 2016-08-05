<?php

class Logs {
    private $db = null;
    
    public function __construct ($app) {
        $this->db = $app->db;
    }

    public function getAll() {
        $sql = "SELECT * FROM logs ORDER BY create_time DESC";
        $result = $this->db->fetchAll($sql);
        if ($result && count($result) > 0) {
            return $result;
        }

        return null;
    }
}