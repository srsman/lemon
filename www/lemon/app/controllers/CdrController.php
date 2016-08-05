<?php

use Phalcon\Mvc\Controller;

class CdrController extends ControllerBase {

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
        if (!$this->acl->isAllowed($this->role, "Cdr", "index")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $this->view->user = $this->user;
        $this->view->pick("cdr/index");
        return true;
    }
  

    public function queryAction() {
        if (!$this->acl->isAllowed($this->role, "Cdr", "query")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        if ($this->request->getQuery('action', 'alphanum') === 'query') {
            $cdr = new Cdr($this);
            $this->view->user = $this->user;
            $this->view->cdrs = $cdr->query($this->user->company, $this->request->getQuery());
            $this->view->where = $cdr->whereFilter($this->request->getQuery());
            $this->view->pick("cdr/query");
            return true;
        }

        $this->view->user = $this->user;
        $this->view->cdrs = null;
        $this->view->where = ['start' => date('Y-m-d 08:00:00'), 'end' => date('Y-m-d 20:00:00'), 'billsec' => 0];
        $this->view->pick("cdr/query");
        return true;
    }

    public function reportAction() {
        if (!$this->acl->isAllowed($this->role, "Cdr", "report")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }
        
        $company = new Company($this);
        $users = $company->get_all_user($this->user->company);
        $report = null;
        
        if ($this->request->getQuery('action', 'alphanum') === 'query') {
            $cdr = new Cdr($this);
            if ($users != null) {
                $result = $cdr->report($this->user->company, $this->request->getQuery());
                foreach ($users as $user) {
                    $uid = $user['uid'];
                    $report[$uid] = ['uid' => $uid, 'name' => $user['name'], 'icon' => $user['icon'], 'total' => 0, 'call_in' => 0, 'call_out' => 0, 'talktime' => 0];
                    foreach ($result as $data) {
                        if ($data['caller_id_number'] == $uid || $data['destination_number'] == $uid) {
                            $report[$uid]['total'] += 1;
                            if ($data['caller_id_number'] == $uid) {
                                $report[$uid]['call_out'] += 1;
                            } else {
                                $report[$uid]['call_in'] += 1;
                            }
                            $report[$uid]['talktime'] += $data['billsec'];
                        }
                    }
                }
            }

            $this->view->user = $this->user;
            $this->view->report = $report;
            if ($this->request->hasQuery('export') && $report != null && count($report) > 0) {
                foreach ($report as $data) {
                    $uid = $data['uid'];
                    unset($report[$uid]['icon']);
                    $report[$uid]['talktime'] = gmstrftime('%H:%M:%S', $data['talktime']);
                    if ($data['total'] > 0) {
                        $report[$uid]['average'] = gmstrftime('%H:%M:%S', intval($data['talktime'] / $data['total']));
                    } else {
                        $report[$uid]['average'] = '00:00:00';
                    }
                    $report[$uid]['total'] = strval($data['total']);
                    $report[$uid]['call_in'] = strval($data['call_in']);
                    $report[$uid]['call_out'] = strval($data['call_out']);
                }

                $excel = $this->phpexcel;
                // set active sheet
                $excel->setActiveSheetIndex(0);
                $excel->getActiveSheet()->setTitle('report');
                $excel->getActiveSheet()->setCellValue('A1', '座席帐号');
                $excel->getActiveSheet()->setCellValue('B1', '座席姓名');
                $excel->getActiveSheet()->setCellValue('C1', '通话总数');
                $excel->getActiveSheet()->setCellValue('D1', '呼入总数');
                $excel->getActiveSheet()->setCellValue('E1', '呼出总数');
                $excel->getActiveSheet()->setCellValue('F1', '通话时长');
                $excel->getActiveSheet()->setCellValue('G1', '平均通话时长');
                $excel->getActiveSheet()->fromArray($report, null, 'A2');
        
                // Redirect output to a client’s web browser (Excel5)
                header('Content-Type: application/vnd.ms-excel');
                header('Content-Disposition: attachment;filename="通话记录报表.xls"');
                header('Cache-Control: max-age=0');

                $objWriter = PHPExcel_IOFactory::createWriter($excel, 'Excel5');
                $objWriter->save('php://output');
                exit(0);
            }

            $this->view->where = $cdr->forReportFilter($this->request->getQuery());
            $this->view->pick("cdr/report");
            return true;
        }

        if (is_array($users) && count($users) > 0) {
            foreach ($users as $user) {
                $uid = $user['uid'];
                $report[$uid] = ['uid' => $uid, 'name' => $user['name'], 'icon' => $user['icon'], 'total' => 0, 'call_in' => 0, 'call_out' => 0, 'talktime' => 0];
            }
        }
        
        $this->view->report = $report;
        $this->view->user = $this->user;
        $this->view->where = ['start' => date('Y-m-d 08:00:00'), 'end' => date('Y-m-d 20:00:00')];
        $this->view->pick("cdr/report");
        return true;
    }
}
