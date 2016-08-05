<?php

use Phalcon\Mvc\Controller;

class ControllerBase extends Controller {
    
    public function checkLogin($redis) {
        if (!$redis) {
            return false;
        }

        /* Login×´Ì¬¼ì²â */
        if ($this->cookies->has('s_uuid')) {
            $uuid = $this->cookies->get('s_uuid')->getValue('alphanum');
            if ($this->cookies->has('s_token')) {
                $token = $this->cookies->get('s_token')->getValue('alphanum');
                $ipaddr = $_SERVER["REMOTE_ADDR"];

                $reply = $redis->hMGet('session.'.$uuid, ['token', 'ipaddr']);
                if ($reply['token'] === $token) {
                    if ($reply['ipaddr'] === $ipaddr) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function userInit() {
        $uid = $this->cookies->get('s_uuid')->getValue('alphanum');
        $reply = $this->redis->hGetAll('user.'.$uid);
        return (object)$reply;
    }

    public function logs($level = 0, $operator = 'null', $content = 'no content!') {
        $data['level'] = intval($level);
        $data['operator'] = str_replace(" ", "", strval($operator));
        $data['ipaddr'] = $_SERVER["REMOTE_ADDR"];
        $data['content'] = str_replace(" ", "", $content);
        $data['create_time'] = date('Y-m-d H:i:s');

        $this->db->insertAsDict('logs', $data);
        return true;
    }
}
