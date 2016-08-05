<?php

use Phalcon\Mvc\Controller;

class ErrorController extends ControllerBase {

    public function beforeExecuteRoute() {
        if (!$this->checkLogin($this->redis)) {
            $this->response->redirect('login');
            return false;
        }
    }

    public function initialize() {
        $this->user = $this->userInit();

        if ($this->user->type == '0') {
            $this->role = 'Super';
        } else if ($this->user->type == '1') {
            $this->role = 'Administrator';
        } else if ($this->user->type == '2') {
            $this->role = 'Quality';
        } else if ($this->user->type == '3') {
            $this->role = 'Agent';
        } else {
            $this->role = 'Other';
        }
    }

    public function indexAction() {
        if (!$this->acl->isAllowed($this->role, "Error", "index")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $this->view->pick("error/404");
        return true;
    }

    public function rejectAction() {
        if (!$this->acl->isAllowed($this->role, "Error", "reject")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }
        
        $this->view->pick("error/reject");
        return true;
    }
}
