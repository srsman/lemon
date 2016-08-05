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
        if (!$this->acl->isAllowed($this->role, "Sound", "index")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $company = new Company($this);
        $this->view->user = $this->user;
        $this->view->sounds = $company->getSounds($this->user->company);
        $this->view->pick("sound");
        return true;
    }

    public function createAction() {
        if (!$this->acl->isAllowed($this->role, "Sound", "create")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        if ($this->request->isPost()) {
            if ($this->request->hasFiles()) {
                $sound = new Sound($this);
                $sound->create($this->user->uid, $this->user->company, $this->request->getPost(), $_FILES);
            }
        }

        $this->response->redirect('sound');
        $this->view->disable();
        return true;
    }

    public function updateAction() {
        if (!$this->acl->isAllowed($this->role, "Sound", "update")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }
        
        if ($this->request->isPost()) {
            $sound_id = intval($this->request->getPost('id', 'int'));
            if ($this->checkPermiss($sound_id)) {
                $sound = new Sound($this);
                $success = $sound->update($sound_id, $this->request->getPost());
            }
        }

        $this->response->redirect('sound');
        $this->view->disable();
        return true;
    }

    private function checkPermiss($sound_id = null) {
        $sound_id = intval($sound_id);

        $sound = new Sound($this);
        if ($sound->isExist($sound_id)) {
            $rep = $sound->get($sound_id);
            return ($rep['company'] === $this->user->company);
        }

        return false;
    }
}
