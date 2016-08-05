<?php

use Phalcon\Mvc\Controller;

class CompanyController extends ControllerBase {

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
        if (!$this->acl->isAllowed($this->role, "Company", "index")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $company = new Company($this);
        $result = $company->getAll();
        $companys = null;
        if (is_array($result) && count($result) > 0) {
            foreach ($result as $buff) {
                $buff['agent'] = $company->getAgentTotal($buff['id']);
                $companys[] = $buff;
            }
        }

        $this->view->companys = $companys;
        $this->view->user = $this->user;
        $this->view->pick("company/index");
        return true;
    }

    public function createAction() {
        if (!$this->acl->isAllowed($this->role, "Company", "create")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        if ($this->request->isPost()) {
            $company = new Company($this);
            $company->create($this->request->getPost());
            $this->response->redirect('company');
            $this->view->disable();
            return true;
        }

        $this->view->user = $this->user;
        $this->view->pick("company/create");
        return true;
    }

    public function editAction($company_id = null) {
        if (!$this->acl->isAllowed($this->role, "Company", "edit")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $company_id = intval($company_id);

        if ($company_id > 0) {
            $company = new Company($this);
            $reslut = $company->get($company_id);
            if ($reslut != null && count($reslut) > 0) {
                $this->view->company = $reslut;
                $this->view->user = $this->user;
                $this->view->pick("company/edit");
                return true;
            }
        }

        $this->response->redirect('error');
        $this->view->disable();
        return true;
    }

    public function updateAction($company_id = null) {
        if (!$this->acl->isAllowed($this->role, "Company", "update")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $company_id = intval($company_id);

        if ($company_id > 0) {
            if ($this->request->isPost()) {
                $company = new Company($this);
                $company->update($company_id, $this->request->getPost());
            }
        }

        $this->response->redirect('company');
        $this->view->disable();
        return true;
    }

    public function deleteAction($company_id = null) {
        if (!$this->acl->isAllowed($this->role, "Company", "delete")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $company_id = intval($company_id);

        if ($company_id > 0) {
            $esl = new ESLconnection(ESL_HOST, ESL_PORT, ESL_PASSWORD);
            $company = new Company($this);
            $company->delete($esl, $company_id);
            if ($esl) {
                $esl->disconnect();
            }
        }

        $this->view->disable();
        return true;
    }

    public function attackAction($company_id = null) {
        if (!$this->acl->isAllowed($this->role, "Company", "attack")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }
        
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $c = new Company($this);
            $soul = $c->doppelganger($company_id);
            if ($soul) {
                echo "<script type=\"text/javascript\">window.location.href='http://" . $_SERVER['SERVER_ADDR'] . ":8088/status';</script>";
                $this->view->disable();
                return true;
            }
        }

        $this->response->redirect('company');
        $this->view->disable();
        return true;
    }
}
