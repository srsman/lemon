<?php

use Phalcon\Mvc\Controller;

class AgentController extends ControllerBase {

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
        if (!$this->acl->isAllowed($this->role, "Agent", "index")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $sql = "SELECT min(id) FROM company";
        $result = $this->db->fetchOne($sql);
        $company_id = 0;
        if ($result) {
            $company_id = intval($result['min']);
        }

        $company = new Company($this);
        $this->view->user = $this->user;
        $this->view->company_id = $company_id;

        $result = $company->getAll();
        $companys = null;
        if ($result != null) {
            foreach ($result as $tmp) {
                $companys[$tmp['id']] = $tmp;
            }
        }
        
        $this->view->companys = $companys;
        $this->view->agents = $company->getAgent($company_id);
        $this->view->pick("agent/index");
        return true;
    }

    public function listAction($company_id = null) {
        if (!$this->acl->isAllowed($this->role, "Agent", "list")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $company_id = intval($company_id);

        if ($company_id > 0) {
            $company = new Company($this);
            if ($company->isExist($company_id)) {
                $result = $company->getAll();
                $companys = null;
                if ($result != null) {
                    foreach ($result as $tmp) {
                        $companys[$tmp['id']] = $tmp;
                    }
                }
            
                $this->view->companys = $companys;
                $this->view->user = $this->user;
                $this->view->company_id = $company_id;
                $this->view->agents = $company->getAgent($company_id);
                $this->view->pick("agent/list");
                return true;
            }
        }

        $this->response->redirect('agent');
        $this->view->disable();
        return true;
    }

    public function createAction($company_id = null) {
        if (!$this->acl->isAllowed($this->role, "Agent", "create")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $company_id = intval($company_id);

        /* check company id */
        if ($company_id > 0) {
            if ($this->request->isPost()) {

                /* check create agent count */
                $count = intval($this->request->getPost('count', 'int'));
                if ($count < 1 || $count > 120) {
                    goto skip;
                }
                
                /* check agent passowrd */
                $password = $this->request->getPost('password', 'string');
                $len = mb_strlen($password, 'utf-8');
                if ($len < 8) {
                    goto skip;
                }

                /* get current max uid */
                $sql = "SELECT max(uid) FROM users WHERE type in (2, 3) AND company = " . $company_id;
                $result = $this->db->fetchOne($sql);
                $start = intval($result['max']);
                if ($start < 1000) {
                    if ($company_id > 0 && $company_id < 10) {
                        $start = intval(str_pad(strval($company_id), 4, "0", STR_PAD_RIGHT)) + 1;
                    } else if ($company_id > 9 && $company_id < 100) {
                        $start = intval(str_pad(strval($company_id), 5, "0", STR_PAD_RIGHT)) + 1;
                    } else {
                        goto skip;
                    }
                } else {
                    $start++;
                }

                $agent = new Agent($this);
                $agent->batchCreate($company_id, $start, $password, $count);
                $this->response->redirect('agent/list/' . $company_id);
                $this->view->disable();
                return true;

            }

            skip:

            $company = new Company($this);
            $result = $company->get($company_id);
            if ($result != null) {
                $this->view->user = $this->user;
                $this->view->company = $result;
                $this->view->pick("agent/create");
                return true;
            }
        }

        $this->response->redirect('error');
        $this->view->disable();
        return true;
    }

    public function batchAction($company_id = null) {
        if (!$this->acl->isAllowed($this->role, "Agent", "batch")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $company_id = intval($company_id);

        if ($company_id > 0) {
            if ($this->request->isPost()) {
                $agent = new Agent($this);
                $agent->batchUpdate($company_id, $this->request->getPost());
                $this->response->redirect('agent/list/' . $company_id);
                $this->view->disable();
                return true;
            }

            $company = new Company($this);
            $result = $company->get($company_id);
            if ($result != null) {
                $this->view->user = $this->user;
                $this->view->company = $result;
                $this->view->pick("agent/batch");
                return true;
            }
        }

        $this->response->redirect('error');
        $this->view->disable();
        return true;
    }

    public function deleteAction($uid = null) {
        if (!$this->acl->isAllowed($this->role, "Agent", "delete")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $agent = new Agent($this);
        $agent->delete($uid);
        $this->view->disable();
        return true;
    }

    public function getAgentAction($uid = null) {
        if (!$this->acl->isAllowed($this->role, "Agent", "getAgent")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }
        
        $agent = new Agent($this);
        $reply = $agent->get($uid);
        if ($reply != null) {
            if ($reply['type'] == '2') {
                $reply['type'] = '质检座席';
            } else if ($reply['type'] == '3') {
                $reply['type'] = '普通座席';
            } else {
                $reply['type'] = '未知类型';
            }

            if ($reply['status'] == '0') {
                $reply['status'] = '已禁用';
            } else if ($reply['status'] == '1') {
                $reply['status'] = '已启用';
            }
            
            if ($reply['web'] == '0') {
                $reply['web'] = '否';
            } else if ($reply['web'] == '1') {
                $reply['web'] = '是';
            }

            if ($reply['calls'] == '0') {
                $reply['calls'] = '否';
            } else if ($reply['calls'] == '1') {
                $reply['calls'] = '是';
            }
                        
            echo json_encode($reply);
        } else {
            $this->response->setStatusCode(404, "Not Found")->sendHeaders();
        }

        $this->view->disable();
        return true;
    }
}
