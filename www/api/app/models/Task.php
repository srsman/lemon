<?php

class Task {
    private $redis = null;
    
    public function __construct ($app) {
        $this->redis = $app['redis'];
    }

    public function isExist($task_id = null) {
        $task_id = intval($task_id);

        if ($task_id > 0) {
            return $this->redis->exists('task.' . $task_id);
        }
        
        return false;
    }

    public function get($task_id = null) {
        $task_id = intval($task_id);

        if ($task_id > 0 && $this->isExist($task_id)) {
            $reply = $this->redis->hMGet('task.' . $task_id, ['type', 'dial', 'play', 'sound', 'total', 'answer', 'complete']);
            if ($reply) {
                return $reply;
            }
        }

        return null;
    }

    public function get_number($task_id = null) {
        $task_id = intval($task_id);

        if ($task_id > 0 && $this->isExist($task_id)) {
            $reply = $this->redis->lPop('data.' . $task_id);
            if ($reply) {
                return $reply;
            }
        }

        return 'unknown';
    }

    public function answer($task_id = null) {
        $task_id = intval($task_id);

        if ($task_id > 0 && $this->isExist($task_id)) {
            $this->redis->hIncrBy('task.' . $task_id, 'answer', 1);
            return true;
        }

        return false;
    }

    public function complete($task_id = null) {
        $task_id = intval($task_id);

        if ($task_id > 0 && $this->isExist($task_id)) {
            $this->redis->hIncrBy('task.' . $task_id, 'complete', 1);
            return true;
        }

        return false;
    }
}
