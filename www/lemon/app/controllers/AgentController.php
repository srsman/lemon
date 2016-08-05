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
        if (!$this->acl->isAllowed($this->role, "Agent", "index")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $agent = new Agent($this);
        $this->view->orders = $agent->getTodayOrder($this->user->uid);

        $company = new Company($this);
        $result = $company->getProduct($this->user->company);
        $products = null;
        if ($result != null) {
            foreach ($result as $product) {
                $products[$product['id']] = $product;
            }
        }
        $this->view->products = $products;

        $this->view->user = $this->user;
        $this->view->pick("agent/todayOrder");
        return true;
    }

    public function orderAction($id = null) {
        if (!$this->acl->isAllowed($this->role, "Agent", "order")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $agent = new Agent($this);
        $this->view->orders = $agent->getOrder($this->user->uid, 0);

        $company = new Company($this);
        $result = $company->getProduct($this->user->company);
        $products = null;
        if ($result != null) {
            foreach ($result as $product) {
                $products[$product['id']] = $product;
            }
        }
        $this->view->products = $products;

        $this->view->user = $this->user;
        $this->view->pick("agent/order");
        return true;
    }

    public function todayOrderAction() {
        if (!$this->acl->isAllowed($this->role, "Agent", "todayOrder")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $agent = new Agent($this);
        $this->view->orders = $agent->getTodayOrder($this->user->uid);

        $company = new Company($this);
        $result = $company->getProduct($this->user->company);
        $products = null;
        if ($result != null) {
            foreach ($result as $product) {
                $products[$product['id']] = $product;
            }
        }
        $this->view->products = $products;

        $this->view->user = $this->user;
        $this->view->pick("agent/todayOrder");
        return true;
    }

    public function getStatusAction() {
        if (!$this->acl->isAllowed($this->role, "Agent", "getStatus")) {
            echo '0';
            return true;
        }

        $agent = new Agent($this);
        $result = $agent->getStatus($this->user->uid);
        if ($result != null) {
            if ($result['status'] == 'Logged Out') {
                echo '0';
            } else if ($result['state'] == 'Idle') {
                echo '1';
            } else if ($result['status'] == 'Available') {
                echo '2';
            } else {
                echo '0';
            }
        } else {
            echo '0';
        }

        $this->view->disable();
        return true;
    }

    public function messagesAction() {
        if (!$this->acl->isAllowed($this->role, "Agent", "messages")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $this->view->user = $this->user;
        $this->view->pick("agent/messages");
        return true;
    }

    public function getOrderAction($id = null) {
        if (!$this->acl->isAllowed($this->role, "Agent", "getOrder")) {
            echo 'Permission denied';
            return true;
        }

        $agent = new Agent($this);
        $result = $agent->getOrder($this->user->uid, $id);
        if ($result != null) {
            $response['last'] = intval($result[count($result) - 1]['id']);

            $product = new Product($this);

            $orders = null;
            foreach ($result as $order) {
                $order['name'] = mb_substr($order['name'], 0, 4, 'utf-8');
                $order['phone'] = mb_substr($order['phone'], 0, 11, 'utf-8');

                $pObject = $product->get($order['product']);
                if ($pObject != null) {
                    $order['product'] = $pObject['name'] . '(' . $pObject['price'] . ')';
                } else {
                    $order['product'] = '未知商品(0.00)';
                }

                $orders[] = $order;
            }

            $response['data'] = $orders;
            echo json_encode($response);
        } else {
            echo 'null';
        }

        $this->view->disable();
        return true;
    }

    public function addAction() {
        if (!$this->acl->isAllowed($this->role, "Agent", "add")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }
        
        if ($this->request->isPost()) {
            $order = new Order($this);
            $success = $order->create($this->user->uid, $this->user->company, $this->request->getPost());
            if ($success) {
                $this->view->pick('agent/order.success');
            } else {
                $this->view->pick('agent/order.failed');
            }
            return true;
        }

        $company = new Company($this);
        $this->view->products = $company->getProduct($this->user->company);
        $this->view->pick("agent/add");
        return true;
    }

    public function getCalledAction() {
        if (!$this->acl->isAllowed($this->role, "Agent", "getCalled")) {
            echo '';
            $this->view->disable();
            return true;
        }
        
        $agent = new Agent($this);
        $called = $agent->getCurrCalled($this->user->uid);
        echo ltrim($called, '0');
        $this->view->disable();
        return true;
    }
}
