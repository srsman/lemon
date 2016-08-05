<?php

use Phalcon\Mvc\Controller;

class UserController extends ControllerBase {
   
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
        if (!$this->acl->isAllowed($this->role, "User", "index")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }
        
        $company = new Company($this);
        $users = $company->get_all_user($this->user->company);
        $this->view->agents = $users;
        $this->view->user = $this->user;
        $this->view->pick("user/index");
        return true;
    }

    public function editAction($uid = null) {
        if (!$this->acl->isAllowed($this->role, "User", "edit")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        if ($uid != null) {
            $uid = $this->filter->sanitize($uid, 'alphanum');
            if ($this->checkPermiss($uid)) {
                $user = new User($this);
                if ($user->isExist($uid)) {
                    $this->view->user = $this->user;
                    $this->view->agent = $user->get($uid);
                    $this->view->pick("user/edit");
                    return true;
                }
            }
        }
        
        $this->response->redirect('error');
        $this->view->disable();
        return false;
    }

    public function settingAction() {
        if (!$this->acl->isAllowed($this->role, "User", "setting")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }
        
        $this->view->pick("setting");
        return true;
    }

    private function checkPermiss($uid = null) {
        $user = new User($this);
        if ($user->isExist($uid)) {
            $agent = $user->get($uid, ['company']);
            return ($agent['company'] === $this->user->company);
        }

        return false;
    }
}
