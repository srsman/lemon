<?php

class Task {
    private $db = null;
    private $pbx = null;
    private $redis = null;
    
    public function __construct ($app) {
        $this->db = $app->db;
        $this->pbx = $app->pbx;
        $this->redis = $app->redis;
    }

    public function isExist($task_id = null) {
        $task_id = intval($task_id);

        if ($task_id > 0) {
            return $this->redis->exists('task.' . $task_id);
        }

        return false;
    }

    public function isCompanyExist($company_id = null) {
        $company_id = intval($company_id);
        if ($company_id > 0) {
            return $this->redis->exists('company.' . $company_id);
        }

        return false;
    }

    public function get($task_id = null) {
        $task_id = intval($task_id);

        if ($task_id > 0 && $this->isExist($task_id)) {
            $column = ['type', 'name', 'business', 'dial', 'play', 'sound', 'total', 'answer', 'complete'];
            $reply = $this->redis->hMGet('task.' . $task_id, $column);

            $reply['id'] = $task_id;

            if ($reply['type'] == '1') {
                $reply['type'] = '群呼转座席';
            } else if ($reply['type'] == '2') {
                $reply['type'] = '群呼转座席';
            } else if ($reply['type'] == '3') {
                $reply['type'] = '点击式外呼';
            } else {
                $reply['type'] = '未知类型';
            }

            if ($reply['business'] == 1) {
                $reply['business'] = '订单模板';
            } else {
                $reply['business'] = '未知模板';
            }

            if ($reply['play'] == '1') {
                $reply['play'] = '已开启';
            } else {
                $reply['play'] = '未开启';
            }

            $sound = $this->getSound($reply['sound']);
            $reply['sound'] = $sound['name'];

            $reply['total'] = intval($reply['total']);
            $reply['answer'] = intval($reply['answer']);
            $reply['complete'] = intval($reply['complete']);
            $reply['remainder'] = $this->getTaskRemainder($task_id);

            if ($reply['total'] > $reply['remainder']) {
                $reply['answer_rate'] = intval(($reply['answer'] / ($reply['total'] - $reply['remainder'])) * 100.0);
            } else {
                $reply['answer_rate'] = 0;
            }

            if ($reply['answer'] > 0) {
                $reply['complete_rate'] = intval(($reply['complete'] / $reply['answer']) * 100.0);
            } else {
                $reply['complete_rate'] = 0;
            }

            return $reply;
        }

        $task = ['id' => 0, 'type' => '未知类型', 'name' => 'No Task',
                 'business' => '未知业务', 'dial' => 0, 'play' => 0,
                 'sound' => '未知语音', 'total' => 0, 'answer' => 0,
                 'complete' => 0, 'remainder' => 0, 'answer_rate' => 0,
                 'complete_rate' => 0];

        return $task;
    }

    public function getAll() {
        $tasks = null;
        $sql = "SELECT id, name FROM company ORDER BY id";
        $result = $this->db->fetchAll($sql);
        if ($result && count($result) > 0) {
            foreach ($result as $company) {
                $task = $this->get($this->getCurrTask($company['id']));
                if ($task != null) {
                    $task['company_id'] = $company['id'];
                    $task['company_name'] = $company['name'];
                    $tasks[] = $task;
                }
            }
        }

        return $tasks;
    }

    public function getCurrTask($company_id = null) {
        $company_id = intval($company_id);

        if ($company_id > 0 && $this->isCompanyExist($company_id)) {
            $reply = $this->redis->hGet('company.' . $company_id, 'task');
            return intval($reply);
        }

        return 0;
    }

    public function getSound($sound_id = null) {
        $sound_id = intval($sound_id);

        if ($sound_id > 0) {
            $reply = $this->redis->hMGet('sound.' . $sound_id, ['name']);
            return $reply;
        }

        return ['name' => '未知语音'];
    }

    public function getTaskRemainder($task_id = null) {
        $task_id = intval($task_id);

        if ($task_id > 0) {
            $reply = $this->redis->lLen('data.' . $task_id);
            return intval($reply);
        }

        return 0;
    }
}
