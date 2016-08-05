<?php

use Phalcon\Mvc\Controller;

class LoginController extends Controller {

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
                $this->redis->setTimeout('session.'.$username, 43200);

                /* 写入用户端cookie */
                $this->cookies->set('uuid', $username, time() + 43200);
                $this->cookies->set('token', $token, time() + 43200);

                $user = $this->getUser($username);
                if ($user != null) {
                    if ($user['type'] === '1') {
                        $this->response->redirect("status");
                    } else if ($user['type'] === '2') {
                        $this->response->redirect("order");
                    } else if ($user['type'] === '3') {
                        $this->response->redirect("agent");
                    } else {
			            $this->view->info = '您输入的账号或者密码不正确';
                        $this->view->pick("login");
			            return true;
                    }

                    $this->db->updateAsDict('users', ['last_login' => date('Y-m-d H:i:s'), 'last_ipaddr' => $_SERVER["REMOTE_ADDR"]], "uid = '" . $user['uid'] . "'");
                    $this->redis->hMSet('user.' . $user['uid'], ['last_login' => date('Y-m-d H:i:s'), 'last_ipaddr' => $_SERVER["REMOTE_ADDR"]]);
                    $this->view->disable();
                    return true;
                }

                $this->response->redirect("login");
                $this->view->disable();
                return true;
            } else {
                $this->view->info = '您输入的账号或者密码不正确';
            }
        }
        
        $this->view->pick("login");
	return true;
    }

    private function check($username, $password) {
        if (empty($username) || empty($password)) {
            return false;
        }

        $reply = $this->redis->hGet('user.'.$username, 'password');
        if ($reply === $password) {
            return true;
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
