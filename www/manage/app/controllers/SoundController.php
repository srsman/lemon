<?php

use Phalcon\Mvc\Controller;

class SoundController extends ControllerBase {

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
        if (!$this->acl->isAllowed($this->role, "Sound", "index")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $sound = new Sound($this);
        $this->view->sounds = $sound->getAll();

        $company = new Company($this);
        $result = $company->getAll();
        $companys = null;
        if ($result != null) {
            foreach ($result as $obj) {
                $companys[$obj['id']] = $obj;
            }
        }
        
        $this->view->companys = $companys;
        $this->view->user = $this->user;
        $this->view->pick("sound/index");
        return true;
    }

    public function passAction($sound_id = null) {
        if (!$this->acl->isAllowed($this->role, "Sound", "pass")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $sound_id = intval($sound_id);

        if ($sound_id > 0) {
            $sound = new Sound($this);
            $sound->pass($sound_id);
        }

        $this->response->redirect('sound');
        $this->view->disable();
        return true;
    }

    public function rejectAction($sound_id = null) {
        if (!$this->acl->isAllowed($this->role, "Sound", "reject")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $sound_id = intval($sound_id);

        if ($sound_id > 0) {
            $sound = new Sound($this);
            $sound->reject($sound_id);
        }

        $this->response->redirect('sound');
        $this->view->disable();
        return true;
    }

    public function uploadAction() {
        if (!$this->acl->isAllowed($this->role, "Sound", "upload")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        if ($this->request->isPost()) {
            $sound = new Sound($this);
            $sound->upload($this->user->uid, $this->request->getPost(), $_FILES);
            $this->response->redirect('sound');
            $this->view->disable();
            return true;
        }

        $this->view->user = $this->user;
        $this->view->pick("sound/upload");
        return true;
    }

    public function editAction($sound_id = null) {
        if (!$this->acl->isAllowed($this->role, "Sound", "edit")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $sound_id = intval($sound_id);

        if ($sound_id > 0) {
            $sound = new Sound($this);
            $result = $sound->get($sound_id);
            if ($result != null) {
                $this->view->user = $this->user;
                $this->view->sound = $result;

                $company = new Company($this);
                $result = $company->getAll();
                $companys = null;
                if ($result != null) {
                    foreach ($result as $obj) {
                        $companys[$obj['id']] = $obj;
                    }
                }

                $this->view->companys = $companys;
                $this->view->pick("sound/edit");
                return true;
            }
        }

        $this->response->redirect('sound');
        $this->view->disable();
        return true;
    }

    public function updateAction($sound_id = null) {
        if (!$this->acl->isAllowed($this->role, "Sound", "update")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $sound_id = intval($sound_id);

        if ($sound_id > 0) {
            if ($this->request->isPost()) {
                $sound = new Sound($this);
                $sound->update($sound_id, $this->request->getPost(), $_FILES);
            }
        }

        $this->response->redirect('sound');
        $this->view->disable();
        return true;
    }
    
    public function deleteAction($sound_id = null) {
        if (!$this->acl->isAllowed($this->role, "Sound", "delete")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }
        
        $sound_id = intval($sound_id);

        if ($sound_id > 0) {
            $sound = new Sound($this);
            $sound->delete($sound_id);
        }

        $this->response->redirect('sound');
        $this->view->disable();
        return true;
    }
}
