<?php

class Company {
    private $redis = null;
    
    public function __construct ($app) {
        $this->redis = $app['redis'];
    }

    public function isExist($company_id = null) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            return $this->redis->exists('company.' . $company_id);
        }
        
        return false;
    }

    public function get($company_id = null) {
        $company_id = intval($company_id);

        if ($this->isExist($company_id)) {
            $columns = ['id', 'concurrent', 'billing', 'level', 'task', 'data_filter'];
            $reply = $this->redis->hMGet('company.' . $company_id, $columns);
            return $reply;
        }

        return null;
    }
}
