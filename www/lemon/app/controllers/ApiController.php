<?php

use Phalcon\Mvc\Controller;

class ApiController extends ControllerBase {
    const USER = 1;
    const SOUND = 2;
    
    public function beforeExecuteRoute() {
        if (!$this->checkLogin($this->redis)) {
            $this->response->setStatusCode(401, "Unauthorized")->sendHeaders();
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

        $this->view->disable();
    }
    
    public function user_getAction($uid = null) {
        if (!$this->acl->isAllowed($this->role, "Api", "user_get")) {
            $this->response->setStatusCode(404, "Not Found")->sendHeaders();
            $this->response->setContentType('application/json')->sendHeaders();
            echo '{"status": "err", "message": "Permission denied"}';
            return false;
        }

        if ($uid == null) {
            $this->response->setStatusCode(404, "Not Found")->sendHeaders();
            $this->response->setContentType('application/json')->sendHeaders();
            echo '{"status": "err", "message": "not specify a uid"}';
            return false;
        }


        $uid = $this->filter->sanitize($uid, 'alphanum');
        if ($this->checkPermiss(self::USER, $uid)) {
            $user = new User($this);
        
            $result = $user->get($uid, explode(',', $this->request->getQuery('attr', 'string')));
            if ($result) {
                $data['status'] = 'ok';
                $data['message'] = 'request user success';
                $data['data'] = $result;
                $this->response->setStatusCode(200, "OK")->sendHeaders();
                $this->response->setContentType('application/json')->sendHeaders();
                echo json_encode($data);
                return true;
            }
        }
        
        $this->response->setStatusCode(200, "OK")->sendHeaders();
        $this->response->setContentType('application/json')->sendHeaders();
        echo '{"status": "err", "message": "request user failed"}';
        return false;
    }

    public function user_updateAction($uid = null) {
        if (!$this->acl->isAllowed($this->role, "Api", "user_update")) {
            $this->response->setStatusCode(404, "Not Found")->sendHeaders();
            $this->response->setContentType('application/json')->sendHeaders();
            echo '{"status": "err", "message": "Permission denied"}';
            return false;
        }

        if ($uid == null) {
            $this->response->setStatusCode(404, "Not Found")->sendHeaders();
            $this->response->setContentType('application/json')->sendHeaders();
            echo '{"status": "err", "message": "Invalid user ID"}';
            return false;
        }

        if ($this->request->isPut()) {
            $uid = $this->filter->sanitize($uid, 'alphanum');
            if ($this->checkPermiss(self::USER, $uid)) {
                $user = new User($this);
                $esl = new ESLconnection(ESL_HOST, ESL_PORT, ESL_PASSWORD);

                if ($user->update($esl, $uid, json_decode($this->request->getRawBody(), true))) {
                    $this->response->setStatusCode(200, "OK")->sendHeaders();
                    $this->response->setContentType('application/json')->sendHeaders();
                    echo '{"status": "ok", "message": "数据修改成功"}';
                    return true;
                }

                if ($esl) {
                    $esl->disconnect();
                }
                    
                $this->response->setStatusCode(200, "OK")->sendHeaders();
                $this->response->setContentType('application/json')->sendHeaders();
                echo '{"status": "err", "message": "数据更新失败"}';
                return true;  
            }
        }

        $this->response->setStatusCode(200, "OK")->sendHeaders();
        $this->response->setContentType('application/json')->sendHeaders();
        echo '{"status": "err", "message": "Method Not Allowed"}';
        return false;
    }

    public function sound_getAction($sound_id) {
        if (!$this->acl->isAllowed($this->role, "Api", "sound_get")) {
            $this->response->setStatusCode(404, "Not Found")->sendHeaders();
            $this->response->setContentType('application/json')->sendHeaders();
            echo '{"status": "err", "message": "Permission denied"}';
            return false;
        }

        $sound_id = intval($this->filter->sanitize($sound_id, 'int'));

        if ($this->checkPermiss(self::SOUND, $sound_id)) {
            $sound = new Sound($this);
            $data['status'] = 'ok';
            $data['message'] = '语音数据获取成功';
            $data['data'] = $sound->get($sound_id);
            $this->response->setStatusCode(200, "OK")->sendHeaders();
            $this->response->setContentType('application/json')->sendHeaders();
            echo json_encode($data);
            return true;
        }

        $data['status'] = 'err';
        $data['message'] = '语音数据获取失败';
        $this->response->setStatusCode(200, "OK")->sendHeaders();
        $this->response->setContentType('application/json')->sendHeaders();
        echo json_encode($data);
        return false;
    }

    public function get_statusAction() {
        if (!$this->acl->isAllowed($this->role, "Api", "get_status")) {
            $this->response->setStatusCode(404, "Not Found")->sendHeaders();
            $this->response->setContentType('application/json')->sendHeaders();
            echo '{"status": "err", "message": "Permission denied"}';
            return false;
        }

        /* user */
        $company = new Company($this);
        $result= $company->get_all_user($this->user->company);
        $users = null;
        if ($result != null && count($result) > 0) {
            foreach ($result as $user) {
                $users[$user['uid']] = $user;
            }
        }

        $status = new Status($this);

        /* task */
        $task = $status->getCurrentTask($this->user->company);
        $taskType = intval($task['type']);
        $task['name'] = mb_substr($task['name'], 0, 5, 'utf-8');
        if ($taskType == 1) {
            $task['type'] = '群呼转座席';
        } else if ($taskType == 2) {
            $task['type'] = '群呼转座席';
        } else if ($taskType == 3) {
            $task['type'] = '手动批量外呼';
        } else {
            $task['type'] = '未知类型';
        }

        /* queue */
        $queue = $status->getQueue($this->user->company);
        $queues = null;
        if ($queue != null && count($queue) > 0) {
            foreach ($queue as $agent) {
                $list = ['Available', 'Available (On Demand)', 'On Break'];
                if (in_array($agent['status'], $list)) {
                    /* uid */
                    $agent['uid'] = $agent['name'];

                    /* name */
                    $agent['name'] = $users[$agent['uid']]['name'];

                    /* icon */
                    $agent['icon'] = $users[$agent['uid']]['icon'];

                    /* status */
                    /*
                    if ($agent['status'] == 'Available') {
                        $agent['status'] = '已登录';
                        $agent['statusStyle'] = 'label-success';
                    } else if ($agent['status'] == 'On Break') {
                        $agent['status'] = '打酱油';
                        $agent['statusStyle'] = 'label-danger';
                    }
                    */

                    /* state */
                    if ($agent['status'] == 'On Break') {
                        $agent['state'] = '打酱油';
                        $agent['stateStyle'] = 'label-danger';
                    } else if ($agent['state'] == 'Waiting') {
                        $agent['state'] = '等待中';
                        $agent['stateStyle'] = 'label-default';
                    } else if ($agent['state'] == 'Receiving') {
                        $agent['state'] = '振 铃';
                        $agent['stateStyle'] = 'label-stress';
                    } else if ($agent['state'] == 'In a queue call') {
                        $agent['state'] = '通话中';
                        $agent['stateStyle'] = 'label-success';
                    } else if ($agent['state'] == 'Idle') {
                        $agent['state'] = '已暂停';
                        $agent['stateStyle'] = 'label-warning';
                    } else {
                        $agent['state'] = '未 知';
                        $agent['stateStyle'] = 'label-danger';
                    }

                    if ($agent['no_answer_count'] > 0) {
                        $agent['noAnswerStyle'] = "label label-danger";
                    }

                    /* last_bridge_start */
                    $agent['last_bridge_start'] = date('Y-m-d H:i:s', intval($agent['last_bridge_start']));

                    /* talk_time */
                    $agent['talk_time'] = gmstrftime('%H:%M:%S', $agent['talk_time']);

                    $queues[] = $agent;
                }
            }
        }

        $response['task'] = $task;
        $response['login'] = $status->getLoginAgent($this->user->company);
        $response['talking'] = $status->getTalking($this->user->company);
        $response['playback'] = $status->getPlayback($this->user->company);
        $response['concurrent'] = $status->getConcurrent($this->user->company);
        $response['date'] = date('Y-m-d');
        if ($queues != null) {
            $response['queues'] = $queues;
        } else {
            $response['queues'] = 'null';
        }

        $this->response->setStatusCode(200, "OK")->sendHeaders();
        //$this->response->setContentType('application/json')->sendHeaders();
        echo json_encode($response);
        return true;
    }

    public function cdr_queryAction() {
        if (!$this->acl->isAllowed($this->role, "Api", "cdr_query")) {
            $this->response->setStatusCode(404, "Not Found")->sendHeaders();
            $this->response->setContentType('application/json')->sendHeaders();
            echo '{"status": "err", "message": "Permission denied"}';
            return false;
        }

        $cdr = new Cdr($this);
        $response['data'] = $cdr->query($this->user->company, $this->request->getQuery());
        $response['last'] = $response['data'][count($response['data']) - 1]['id'];
        echo json_encode($response);
        return true;
    }

    public function getRecordAction($phone = null) {
        if (!$this->acl->isAllowed($this->role, "Api", "getRecord")) {
            $this->response->setStatusCode(404, "Not Found")->sendHeaders();
            $this->response->setContentType('application/json')->sendHeaders();
            echo '{"status": "err", "message": "Permission denied"}';
            return false;
        }
        
        if ($phone != null) {
            $order = new Order($this);
            $record = $order->get_orderRecord($this->user->company, $phone);
            if ($record != null) {
                echo $record;
                return true;
            }
        }

        echo 'null.wav';
        return true;
    }

    public function checkmoneyAction() {
        $this->response->setStatusCode(200, "OK")->sendHeaders();
        echo $this->checkMoney($this->user->company);
        return true;
    }

    private function checkPermiss($type, $value) {
        switch ($type) {
            case self::USER:
                /* check user permiss*/
                $uid = $this->filter->sanitize($value, 'alphanum');
                $user = new User($this);
                if ($user->isExist($uid)) {
                    $rep = $user->get($uid, ['company']);
                    return ($rep['company'] === $this->user->company);
                };
                break;
            case self::SOUND:
                $sound_id = intval($value);
                $sound = new Sound($this);
                if ($sound->isExist($sound_id)) {
                    $rep = $sound->get($sound_id);
                    return ($rep['company'] === $this->user->company);
                }
                break;
        default:
            break;
        }
        return false;
    }
}
