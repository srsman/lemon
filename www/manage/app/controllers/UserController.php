<?php

use Phalcon\Mvc\Controller;

class UserController extends ControllerBase {
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
        if (!$this->acl->isAllowed($this->role, "User", "index")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $company = new Company($this);
        $result = $company->getAll();
        $companys = null;
        if ($result != null) {
            foreach ($result as $tmp) {
                $companys[$tmp['id']] = $tmp;
            }
        }

        $user = new User($this);
        $this->view->users = $user->getAll();
        $this->view->companys = $companys;
        $this->view->user = $this->user;
        $this->view->pick("user/index");
        return true;
    }

    public function createAction() {
        if (!$this->acl->isAllowed($this->role, "User", "create")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        if ($this->request->isPost()) {
            $user = new User($this);
            $user->create($this->request->getPost());
            $this->response->redirect('user');
            $this->view->disable();
            return true;
        }

        $company = new Company($this);
        $this->view->companys = $company->getAll();
        $this->view->user = $this->user;
        $this->view->pick("user/create");
        return true;
    }

    public function editAction($uid = null) {
        if (!$this->acl->isAllowed($this->role, "User", "edit")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $user = new User($this);
        $result = $user->get($uid);
        if ($result != null) {
            $company = new Company($this);
            $this->view->companys = $company->getAll();
            $this->view->user = $this->user;
            $this->view->usr = $result;
            $this->view->pick("user/edit");
            return true;
        }
        $this->response->redirect('user');
        $this->view->disable();
        return true;
    }

    public function updateAction($uid = null) {
        if (!$this->acl->isAllowed($this->role, "User", "update")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        if ($this->request->isPost()) {
            $user = new User($this);
            $user->update($uid, $this->request->getPost());
        }

        $this->response->redirect('user');
        $this->view->disable();
        return true;
    }

    public function deleteAction($uid = null) {
        if (!$this->acl->isAllowed($this->role, "User", "delete")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }
        
        $user = new User($this);
        $user->delete($uid);
        $this->response->redirect('user');
        $this->view->disable();
        return true;
    }
}
