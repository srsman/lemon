<?php

class Sound {
    private $redis = null;
    
    public function __construct ($app) {
        $this->redis = $app['redis'];
    }

    public function isExist($sound_id = null) {
        $sound_id = intval($sound_id);

        if ($sound_id > 0) {
            return $this->redis->exists('sound.' . $sound_id);
        }
        
        return false;
    }

    public function get($sound_id = null) {
        $sound_id = intval($sound_id);

        if ($this->isExist($sound_id)) {
            $columns = ['id', 'file', 'company', 'status'];
            $reply = $this->redis->hMGet('sound.' . $sound_id, $columns);
            if ($reply) {
                return $reply;
            }
        }

        return null;
    }
}
