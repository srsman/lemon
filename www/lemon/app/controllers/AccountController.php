<?php

use Phalcon\Mvc\Controller;

class AccountController extends ControllerBase {

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
        if (!$this->acl->isAllowed($this->role, "Account", "index")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }
        
        $company = new Company($this);

        $account = $company->get($this->user->company);
        if ($account != null) {
            $this->view->account = $account;
        } else {
            $this->view->account = ['id' => null, 'name' => 'unknown', 'concurrent' => 0, 'billing' => 'unknown', 'level' => 1, 'sound_check' => 1, 'create_time' => '1970-01-01 08:00:00'];
        }

        $this->view->billing = $company->getBilling($this->user->company);
        $this->view->agent = $company->getAgentTotal($this->user->company);
        $this->view->user = $this->user;
        $this->view->pick("account");
        return true;
    }
}
