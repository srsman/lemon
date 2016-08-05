<?php

use Phalcon\Mvc\Controller;

class TaskController extends ControllerBase {

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
        if (!$this->acl->isAllowed($this->role, "Task", "index")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $task = new Task($this);
        $this->view->tasks = $task->getAll();
        $this->view->user = $this->user;
        $this->view->pick("task");
        return true;
    }

    public function getStatusAction($company_id = null) {
        if (!$this->acl->isAllowed($this->role, "Task", "getStatus")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }
        
        $company_id = intval($company_id);
        
        if ($company_id > 0) {
            $company = new Company($this);
            $status = $company->getStatus($company_id);
            if ($status != null) {
                echo json_encode($status);
                $this->view->disable();
                return true;
            }
        }

        $this->response->setStatusCode(404, "Not Found")->sendHeaders();
        $this->view->disable();
        return true;
    }
}
