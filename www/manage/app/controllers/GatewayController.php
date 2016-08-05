<?php

use Phalcon\Mvc\Controller;

class GatewayController extends ControllerBase {

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
        if (!$this->acl->isAllowed($this->role, "Gateway", "index")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $gateway = new Gateway($this);
        $esl = new ESLconnection(ESL_HOST, ESL_PORT, ESL_PASSWORD);
        
        $this->view->gateways = $gateway->getAll($esl);
        if ($esl) {
            $esl->disconnect();
        }

        $companys = null;
        $company = new Company($this);
        $result = $company->getAll();
        if ($result && count($result) > 0) {
            foreach ($result as $obj) {
                $companys[$obj['id']] = $obj;
            }
        }

        $this->view->companys = $companys;
        $this->view->user = $this->user;
        $this->view->pick("gateway/index");
        return true;
    }

    public function editAction($company_id = null) {
        if (!$this->acl->isAllowed($this->role, "Gateway", "edit")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $company_id = intval($company_id);

        if ($company_id > 0) {
            $gateway = new Gateway($this);
            $obj = $gateway->get($company_id);
            if ($obj != null) {
                $this->view->gateway = $obj;
                $this->view->user = $this->user;
                $company = new Company($this);
                $this->view->company = $company->get($obj['company']);
                $this->view->pick("gateway/edit");
                return true;
            }
        }
        
        $this->response->redirect('error');
        $this->view->disable();
        return true;
    }

    public function updateAction($company_id = null) {
        if (!$this->acl->isAllowed($this->role, "Gateway", "update")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }
        
        $company_id = intval($company_id);

        if ($company_id > 0) {
            if ($this->request->isPost()) {
                $gateway = new Gateway($this);
                $esl = new ESLconnection(ESL_HOST, ESL_PORT, ESL_PASSWORD);
                $gateway->update($esl, $company_id, $this->request->getPost());
                if ($esl) {
                    $esl->disconnect();
                }
            }

            $this->response->redirect('gateway');
            $this->view->disable();
            return true;
        }

        $this->response->redirect('error');
        $this->view->disable();
        return true;
    }
}
