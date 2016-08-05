<?php

use Phalcon\Mvc\Controller;

class ExtenController extends ControllerBase {

    public function beforeExecuteRoute() {
        if (!$this->checkLogin($this->redis)) {
            $this->response->redirect('login');
            return false;
        }
    }
    
    public function initialize() {
        $this->user = $this->userInit();

        if ($this->user->type == '1') {
            $this->role = 'Administrator';
        } else if ($this->user->type == '2') {
            $this->role = 'Quality';
        } else if ($this->user->type == '3') {
            $this->role = 'Agent';
        } else {
            $this->role = 'Guests';
        }
    }

    public function indexAction() {
        if (!$this->acl->isAllowed($this->role, "Exten", "index")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }
        
        $company = new Company($this);
        $this->view->user = $this->user;
        $this->view->extens = $company->get_all_exten($this->user->company);
        $this->view->pick("exten");
    }
}
