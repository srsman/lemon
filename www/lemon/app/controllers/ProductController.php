<?php

use Phalcon\Mvc\Controller;

class ProductController extends ControllerBase {

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
        if (!$this->acl->isAllowed($this->role, "Product", "index")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $company = new Company($this);
        $this->view->user = $this->user;
        $this->view->products = $company->getProduct($this->user->company);
        $this->view->pick("product/index");
    }

    public function editAction ($product_id = null) {
        if (!$this->acl->isAllowed($this->role, "Product", "edit")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        if ($product_id == null) {
            $this->response->redirect('error');
            return false;
        }

        $product_id = intval($product_id);

        if ($this->checkPermiss($product_id)) {
            $product = new Product($this);
            $result = $product->get($product_id);
            if ($result) {
                $this->view->user = $this->user;
                $this->view->product = $result;
                $this->view->pick("product/edit");
                return true;
            }
        }

        $this->response->redirect('error');
        $this->view->disable();
        return false;
    }

    public function createAction() {
        if (!$this->acl->isAllowed($this->role, "Product", "create")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        if ($this->request->isPost()) {
            $data = $this->request->getPost();
            $product = new Product($this);
            $success = $product->create($this->user->company, $data);
        }
        $this->response->redirect('product');
        $this->view->disable();
        return true;
    }

    public function updateAction() {
        if (!$this->acl->isAllowed($this->role, "Product", "update")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        if ($this->request->isPost()) {
            $product_id = intval($this->request->getPost('id', 'int'));
            if ($this->checkPermiss($product_id)) {
                $product = new Product($this);
                $product->update($product_id, $this->request->getPost());
                $this->response->redirect('product');
                $this->view->disable();
                return true;
            }
        }

        $this->response->redirect('error');
        $this->view->disable();
        return false;
    }

    public function deleteAction($product_id = null) {
        if (!$this->acl->isAllowed($this->role, "Product", "delete")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }
        
        if ($product_id != null) {
            $product_id = intval($product_id);
            if ($this->checkPermiss($product_id)) {
                $product = new Product($this);
                $product->delete($product_id);
            }
        }

        $this->response->redirect('product');
        $this->view->disable();
        return true;
    }

    private function checkPermiss($product_id = null) {
        $product = new Product($this);
        if ($product->isExist($product_id)) {
            $result = $product->get($product_id);
            return ($result['company'] === $this->user->company);
        }

        return false;
    }
}
