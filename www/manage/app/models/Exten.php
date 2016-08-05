<?php

class Exten {
    private $pbx = null;
    private $redis = null;
    private $cookies = null;
    private $filter = null;
    
    public function __construct ($app) {
        $this->pbx = $app->pbx;
        $this->redis = $app->redis;
        $this->cookies = $app->cookies;
    }

    public function isExist($exten) {
        $exten = $this->filter->sanitize($exten, 'int');
        return $this->redis->exists('agent.' . $exten);
    }

    public function get($exten) {
        $exten = intval($this->filter->sanitize($exten, 'int'));

        if ($this->isExist($exten)) {
            $result = 
        }
    }
}
