<?php

use Phalcon\Mvc\Controller;

class StatusController extends ControllerBase {
    
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
        if (!$this->acl->isAllowed($this->role, "Status", "index")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }
        
        $status = new Status($this);
        $esl = new ESLconnection(ESL_HOST, ESL_PORT, ESL_PASSWORD);
        $this->view->status = $status->getAll($esl);
        if ($esl) {
            $esl->disconnect();
        }
        $this->view->user = $this->user;
        $this->view->pick("status");
    }
}
