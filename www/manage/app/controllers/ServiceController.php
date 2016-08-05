<?php

use Phalcon\Mvc\Controller;

class ServiceController extends ControllerBase {
    
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
        if (!$this->acl->isAllowed($this->role, "Service", "index")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $service = new Service($this);
        $this->view->services = $service->getAll();
        $this->view->user = $this->user;
        $this->view->pick("service");
        return true;
    }

    public function startAction($company_id = null) {
        if (!$this->acl->isAllowed($this->role, "Service", "start")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $company_id = intval($company_id);

        if ($company_id > 0) {
            $service = new Service($this);
            $service->start($company_id);
        }

        $this->response->redirect('service');
        $this->view->disable();
        return true;
    }

    public function stopAction($company_id = null) {
        if (!$this->acl->isAllowed($this->role, "Service", "stop")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }
        
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $service = new Service($this);
            $service->stop($company_id);
        }

        $this->response->redirect('service');
        $this->view->disable();
        return true;
    }
}
