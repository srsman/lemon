<?php

class Status {
    private $db = null;
    private $pbx = null;
    private $redis = null;
    private $filter = null;
    
    public function __construct ($app) {
        $this->db = $app->db;
        $this->pbx = $app->pbx;
        $this->redis = $app->redis;
        $this->filter = $app->filter;
    }

    public function isCompanyExist($company_id = null) {
        $company_id = intval($company_id);
        if ($company_id > 0) {
            return $this->redis->exists('company.' . $company_id);
        }
        return false;
    }

    public function getCurrentTask($company_id) {
        $company_id = intval($company_id);
        if ($this->isCompanyExist($company_id)) {
            $task_id = intval($this->redis->hGet('company.'.$company_id, 'task'));
            if ($task_id > 0) {
                $reply = $this->redis->hMGet('task.'.$task_id, ['name', 'type', 'total']);
                $reply['remainder'] = intval($this->redis->lLen('data.'.$task_id));
                return $reply;
            }
        }
        
        return ['name' => 'Null', 'type' => 0, 'total' => 0, 'remainder' => 0];
    }

    public function getLoginAgent($company_id = null) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $queue = $company_id . '@queue';
            $sql = "SELECT count(name) FROM agents WHERE name in (SELECT agent FROM tiers WHERE queue = '" . $queue . "') AND status in ('Available', 'Available (On Demand)', 'On Break')";
            $result = $this->pbx->fetchOne($sql);
            if ($result) {
                return intval($result['count']);
            }
        }

        return 0;
    }

    public function getTalking($company_id = null) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $queue = $company_id . '@queue';
            $sql = "SELECT count(name) FROM agents WHERE name in (SELECT agent FROM tiers WHERE queue = '" . $queue . "') AND state = 'In a queue call' AND status in ('Available', 'Available (On Demand)', 'On Break')";
            $result = $this->pbx->fetchOne($sql);
            if ($result) {
                return intval($result['count']);
            }
        }

        return 0;
    }

    public function getPlayback($company_id) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $company_id = strval($company_id);
            $sql = "SELECT count(uuid) FROM channels WHERE application = 'playback' AND initial_cid_num = '" . $company_id . "'";
            $result = $this->pbx->fetchOne($sql);
            if ($result) {
                return intval($result['count']);
            }
        }

        return 0;
    }

    public function getConcurrent($company_id = null) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $company_id = strval($company_id);
            $sql = "SELECT count(uuid) FROM channels WHERE initial_cid_num = '" . $company_id . "'";
            $result = $this->pbx->fetchOne($sql);
            if ($result) {
                return intval($result['count']);    
            }
        }

        return 0;
    }

    public function getQueue($company_id = null) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $queue = $company_id . '@queue';
            $sql = "SELECT name, status, state, last_bridge_start, no_answer_count, calls_answered, talk_time FROM agents WHERE name in (SELECT agent FROM tiers WHERE queue = '" . $queue . "') AND status in ('Available', 'Available (On Demand)', 'On Break') ORDER BY name";
            $result = $this->pbx->fetchAll($sql);
            return $result;
        }

        return null;
    }
}
