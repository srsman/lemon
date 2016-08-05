<?php

use Phalcon\Mvc\Controller;

class LoginController extends ControllerBase {

    public function indexAction() {
        if ($this->request->isPost()) {
            $username = $this->request->getPost('username', 'alphanum');
            $password = $this->request->getPost('password', 'string');

            if ($this->check($username, $password)) {
                /* 生成随机token字符串 */
                $random = new \Phalcon\Security\Random();
                $token = $random->hex(16);
                
                /* 将uuid与token写入redis */
                $this->redis->hMSet('session.'.$username, ['token' => $token, 'ipaddr' => $_SERVER["REMOTE_ADDR"]]);
                $this->redis->setTimeout('session.'.$username, 3600);

                /* 写入用户端cookie */
                $this->cookies->set('s_uuid', $username, time() + 3600);
                $this->cookies->set('s_token', $token, time() + 3600);

                $user = $this->getUser($username);
                if ($user != null) {
                    if ($user['type'] === '0') {
                        $this->logs(0, $user['uid'], '成功登录运营管理平台系统');
                        $this->response->redirect("company");
                        $this->view->disable();
                        return true;
                    }
                }
            }

            $this->logs(1, $username, '尝试登录系统，帐号或密码验证失败');
            $this->view->info = '您输入的账号或者密码不正确';
        }
        
        $this->view->pick("login");
        return true;
    }

    private function check($username, $password) {
        if (empty($username) || empty($password)) {
            return false;
        }

        $password = sha1(md5($password));
        $reply = $this->redis->hGet('user.'.$username, 'password');
        if ($reply === $password) {
            return true;
        }

        return false;
    }

    private function getUser($uid = null) {
        $uid = $this->filter->sanitize($uid, 'alphanum');
        $reply = $this->redis->hMGet('user.' . $uid, ['uid', 'name', 'password', 'type', 'company', 'status', 'web']);
        if (is_array($reply)) {
            foreach ($reply as $attr) {
                if ($attr === false) {
                    return null;
                }
            }
            return $reply;
        }

        return null;
    }
}
