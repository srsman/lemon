<?php

class Exten {
    private $redis = null;
    
    public function __construct ($app) {
        $this->redis = $app->redis;
    }

    public function isExist($exten) {
        $exten = $this->filter->sanitize($exten, 'alphanum');
        return $this->redis->exists('user.' . $exten);
    }
}
