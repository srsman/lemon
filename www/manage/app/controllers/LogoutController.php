<?php

use Phalcon\Mvc\Controller;

class LogoutController extends ControllerBase {

    public function beforeExecuteRoute() {
        if (!$this->checkLogin($this->redis)) {
            $this->response->redirect('login');
            return false;
        }
    }

    public function indexAction() {
        /* clearup session */        
        $this->redis->delete('session.' . $this->cookies->get('s_uuid')->getValue('alphanum'));
        
        /* clearup uuid cookie*/
        $this->cookies->get('s_uuid')->delete();

        /* clearup token cookie*/
        $this->cookies->get('s_token')->delete();

        $this->response->redirect('login');
        $this->view->disable();
    }

}
