<?php

use Phalcon\Mvc\Controller;

class LogsController extends ControllerBase {
    private $user = null;
    
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
        if (!$this->acl->isAllowed($this->role, "Logs", "index")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }
        
        $l = new Logs($this);
        $this->view->events = $l->getAll();
        $this->view->user = $this->user;
        $this->view->pick("logs");
        return true;
    }
}
