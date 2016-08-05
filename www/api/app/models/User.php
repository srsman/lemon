<?php

class User {
    private $redis = null;
    
    public function __construct ($app) {
        $this->redis = $app['redis'];
    }

    public function isExist($uid = null) {
        return $this->redis->exists('user.' . $uid);
    }

    public function get($uid = null) {
        if ($this->isExist($uid)) {
            $columns = ['uid', 'type', 'callerid', 'company', 'status', 'calls', 'last_called'];
            $reply = $this->redis->hMGet('user.' . $uid, $columns);
            if ($reply) {
                return $reply;
            }
        }

        return null;
    }

    public function SetLastCalled($uid = null, $number = null) {
        if ($this->isExist($uid)) {
            $this->redis->hSet('user.' . $uid, 'last_called', $number);
            return true;
        }

        return false;
    }
}
