<?php

use Phalcon\Mvc\Controller;

class TaskController extends ControllerBase {

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
        if (!$this->acl->isAllowed($this->role, "Task", "index")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $company = new Company($this);
        $taskpool = $company->getTaskPool($this->user->company);
        $task = new Task($this);

        $tasks = null;
        if ($taskpool != null && count($taskpool) > 0) {
            foreach ($taskpool as $task_id) {
                $tmp = $task->get($task_id);
                if ($tmp != null) {
                    /* check is run */
                    if ($task->isRun($this->user->company, $task_id)) {
                        $tmp['status'] = true;
                    } else {
                        $tmp['status'] = false;
                    }
                    $tasks[$task_id] = $tmp;
                }
            }
        }

        $this->view->user = $this->user;
        $this->view->tasks = $tasks;
        $this->view->pick("task/index");
        return true;
    }

    public function startAction($task_id = null) {
        if (!$this->acl->isAllowed($this->role, "Task", "start")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $task_id = intval($task_id);

        if ($task_id > 0) {
            if ($this->checkPermiss($task_id)) {
                $task = new Task($this);
                $task->start($this->user->company, $task_id);
            }
        }

        $this->response->redirect('task');
        $this->view->disable();
        return true;
    }

    public function stopAction($task_id = null) {
        if (!$this->acl->isAllowed($this->role, "Task", "stop")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $task_id = intval($task_id);

        if ($task_id > 0) {
            if ($this->checkPermiss($task_id)) {
                $task = new Task($this);
                $task->stop($this->user->company, $task_id);
            }
        }

        $this->response->redirect('task');
        $this->view->disable();
        return true;
    }

    public function editAction($task_id = null) {
        if (!$this->acl->isAllowed($this->role, "Task", "edit")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $task_id = intval($task_id);

        if ($task_id > 0) {
            if ($this->checkPermiss($task_id)) {
                $templet = 'task/error';
                $task = new Task($this);
                $reply = $task->get($task_id);
                if ($reply != null) {
                    switch ($reply['type']) {
                        case 1:
                            $templet = 'task/edit.auto';
                            break;
                        case 2:
                            $templet = 'task/edit.fixed';
                            break;
                        case 3:
                            $templet = 'task/edit.click';
                             break;
                        default:
                            $templet = 'task/error';
                            break;
                    }

                    $company = new Company($this);
                    $this->view->user = $this->user;

                    /* get company concurrent */
                    if ($reply['type'] == 2) {
                        $this->view->company = $company->get($this->user->company);
                    }

                    $this->view->sounds = $company->getPassSounds($this->user->company);
                    $this->view->task = $reply;
                    $this->view->pick($templet);
                    return true;
                }
            }
        }

        $this->response->redirect('error');
        $this->view->disable();
        return false;
    }

    public function updateAction($task_id = null) {
        if (!$this->acl->isAllowed($this->role, "Task", "update")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        $task_id = intval($task_id);
        if ($task_id > 0) {
            if ($this->request->isPost()) {
                if ($this->checkPermiss($task_id)) {
                    $task = new Task($this);
                    $task->update($this->user->company, $task_id, $this->request->getPost());
                }
            }
        }

        $this->response->redirect('task');
        $this->view->disable();
        return true;
    }

    public function createAction () {
        if (!$this->acl->isAllowed($this->role, "Task", "create")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }

        if ($this->request->isPost()) {
            $task = new Task($this);
            $task->create($this->user->company, $this->request->getPost(), $_FILES);
	    sleep(5);
            $this->response->redirect('task');
            $this->view->disable();
            return true;
        }
        
        $company = new Company($this);
        $this->view->user = $this->user;
        $this->view->sounds = $company->getPassSounds($this->user->company);
        $this->view->pick("task/create");
        return true;
    }

    public function deleteAction($task_id = null) {
        if (!$this->acl->isAllowed($this->role, "Task", "delete")) {
            $this->response->redirect('error/reject');
            $this->view->disable();
            return true;
        }
        
        $task_id = intval($task_id);

        if ($task_id > 0) {
            if ($this->checkPermiss($task_id)) {
                $task = new Task($this);
                $task->delete($this->user->company, $task_id);
            }
        }

        $this->view->disable();
        return true;
    }

    private function checkPermiss($task_id = null) {
        $task_id = intval($task_id);

        if ($task_id > 0) {
            $reply = $this->redis->sIsMember('taskpool.' . $this->user->company, strval($task_id));
            if ($reply === true) {
                return true;
            }
        }

        return false;
    }
}
