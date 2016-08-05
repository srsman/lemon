<?php

use Phalcon\Mvc\Controller;

class ErrorController extends ControllerBase {

    public function beforeExecuteRoute() {
        if (!$this->checkLogin($this->redis)) {
            $this->response->redirect('login');
            return false;
        }
    }

    public function indexAction() {
        $this->view->pick("error/404");
        return true;
    }

    public function rejectAction() {
        $this->view->pick("error/reject");
        return true;
    }
}
