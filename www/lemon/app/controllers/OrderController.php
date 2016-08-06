<?php

use Phalcon\Mvc\Controller;

class OrderController extends ControllerBase {

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
        if (!$this->acl->isAllowed($this->role, "Order", "index")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $company = new Company($this);
        $this->view->user = $this->user;
        $this->view->where = ['start' => date('Y-m-d 08:00:00', time()), 'end' => date('Y-m-d 20:00:00', time())];
        $this->view->agents = $company->get_all_user($this->user->company);
        $this->view->pick("order/index");
        return true;
    }

    public function editAction($order_id = null) {
        if (!$this->acl->isAllowed($this->role, "Order", "edit")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $order_id = intval($order_id);
        if ($order_id > 0) {
            if ($this->checkPermiss($order_id)) {
                $order = new Order($this);
                $result = $order->get($order_id);
                if ($result != null) {
                    $this->view->order = $result;

                    $company = new Company($this);
                    $result = $company->getProduct($this->user->company);
                    $products = null;
                    if ($result != null) {
                        foreach ($result as $product) {
                            $products[$product['id']] = $product;
                        }
                    }

                    $result = $company->get_all_user($this->user->company);
                    $agents = null;
                    if ($result != null) {
                        foreach ($result as $agent) {
                            $agents[$agent['uid']] = $agent;
                        }
                    }

                    $this->view->products = $products;
                    $this->view->agents = $agents;
                    $this->view->pick("order/edit");
                    return true;
                }
            }
        }

        $this->view->pick("order/error");
        return true;
    }

    public function queryAction() {
        if (!$this->acl->isAllowed($this->role, "Order", "query")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $where = $this->request->getQuery();
        $company = new Company($this);

        $orders = $company->getOrder($this->user->company, $where);
        $result = $company->getProduct($this->user->company);
        $products = null;

        if ($result != null) {
            foreach ($result as $product) {
                $products[$product['id']] = $product;
            }
        }

       
        $result = $company->get_all_user($this->user->company);
        $agents = null;
        if ($result != null) {
            foreach ($result as $agent) {
                $agents[$agent['uid']] = $agent;
            }
        }

        $this->view->where = $company->whereFilter($where);

        if (isset($this->view->where['export'])) {
            if ($orders != null && $products != null && $agents != null) {
                $this->view->disable();
                $count = count($orders);
                for ($i = 0; $i < $count; $i++) {
                    $orders[$i]['product'] = $products[$orders[$i]['product']]['name'] . '(' . $products[$orders[$i]['product']]['price'] . ')';
                    $orders[$i]['creator'] = $agents[$orders[$i]['creator']]['name'] . '(' . $orders[$i]['creator'] . ')';
                    if ($orders[$i]['status'] == 1) {
                        $orders[$i]['status'] = '待审核';
                    } else if ($orders[$i]['status'] == 2) {
                        $orders[$i]['status'] = '已通过';
                    } else if ($orders[$i]['status'] == 3) {
                        $orders[$i]['status'] = '不通过';
                    } else if ($orders[$i]['status'] == 4) {
                        $orders[$i]['status'] = '已发货';
                    } else if ($orders[$i]['status'] == 5) {
                        $orders[$i]['status'] = '待  定';
                    }
                    unset($orders[$i]['company']);
                    unset($orders[$i]['express_id']);
                    unset($orders[$i]['logistics_status']);
                    unset($orders[$i]['delivery_time']);
                }

                $excel = $this->phpexcel;
                // set active sheet
                $excel->setActiveSheetIndex(0);
                $excel->getActiveSheet()->setTitle('Order');
                $excel->getActiveSheet()->setCellValue('A1', '订单编号');
                $excel->getActiveSheet()->setCellValue('B1', '客户姓名');
                $excel->getActiveSheet()->setCellValue('C1', '手机号码');
                $excel->getActiveSheet()->setCellValue('D1', '固定电话');
                $excel->getActiveSheet()->setCellValue('E1', '商品名称(价格)');
                $excel->getActiveSheet()->setCellValue('F1', '商品数量');
                $excel->getActiveSheet()->setCellValue('G1', '收货地址');
                $excel->getActiveSheet()->setCellValue('H1', '备注信息');
                $excel->getActiveSheet()->setCellValue('I1', '下单座席');
                $excel->getActiveSheet()->setCellValue('J1', '质检员');
                $excel->getActiveSheet()->setCellValue('K1', '审核备注');
                $excel->getActiveSheet()->setCellValue('L1', '订单状态');
                $excel->getActiveSheet()->setCellValue('M1', '下单时间');
                $excel->getActiveSheet()->setCellValue('N1', '审核时间');
                $excel->getActiveSheet()->fromArray($orders, null, 'A2');
        
                // Redirect output to a client’s web browser (Excel5)
                header('Content-Type: application/vnd.ms-excel');
                header('Content-Disposition: attachment;filename="客户订单.xls"');
                header('Cache-Control: max-age=0');

                $objWriter = PHPExcel_IOFactory::createWriter($excel, 'Excel5');
                $objWriter->save('php://output');

                exit(0);
            }
        }
        
        $this->view->orders = $orders;
        $this->view->product = $products;
        $this->view->agents = $agents;
        $this->view->user = $this->user;
        
        $this->view->pick("order/query");
        return true;
    }

    public function updateAction($order_id = null) {
        if (!$this->acl->isAllowed($this->role, "Order", "update")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }
        
        if ($this->request->isPost()) {
            $order_id = intval($order_id);
            if ($order_id > 0) {
                if ($this->checkPermiss($order_id)) {
                    $order = new Order($this);
                    $success = $order->update($order_id, $this->user->uid, $this->request->getPost());
                    if ($success) {
                        $this->view->pick("order/success");
                        return true;
                    } else {
                        $this->view->pick("order/failed");
                        return false;
                    }
                }
            }
        }

        $this->view->pick("order/error");
        return false;
    }

    private function checkPermiss($order_id = null) {
        $order_id = intval($order_id);
        if ($order_id > 0) {
            $order = new Order($this);
            if ($order->isExist($order_id)) {
                $rep = $order->get($order_id);
                if ($rep) {
                    return ($rep['company'] === intval($this->user->company));
                }
            }
        }

        return false;
    }
}
