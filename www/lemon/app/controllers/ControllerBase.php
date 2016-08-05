<?php

use Phalcon\Mvc\Controller;

class ControllerBase extends Controller {
    
    public function checkLogin($redis) {
        if (!$redis) {
            return false;
        }

        /* Login×´Ì¬¼ì²â */
        if ($this->cookies->has('uuid')) {
            $uuid = $this->cookies->get('uuid')->getValue('alphanum');
            if ($this->cookies->has('token')) {
                $token = $this->cookies->get('token')->getValue('alphanum');
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
        $uid = $this->cookies->get('uuid')->getValue('alphanum');
        $reply = $this->redis->hGetAll('user.'.$uid);
        return (object)$reply;
    }

    public function checkMoney($company_id = null) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $c = new Company($this);
            $company = $c->get($company_id);
            if ($company != null) {
                $money = intval($this->radius->hGet($company['billing'], 'money'));
                if ($money < 1) {
                    $this->redis->HSet('company.' . $company_id, 'task', '0');
                    return 1;
                } else if ($money < 150) {
                    return 2;
                }
            }
        }

        return 0;
    }
}
