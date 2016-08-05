<?php

class Task {
    private $db = null;
    private $redis = null;
    private $filter = null;
    
    public function __construct ($app) {
        $this->db = $app->db;
        $this->redis = $app->redis;
        $this->filter = $app->filter;
    }

    public function isExist($task_id) {
        $task_id = intval($task_id);
        if ($task_id > 0) {
            return $this->redis->exists('task.' . $task_id);
        }
        return false;
    }

    /* get a task */
    public function get($task_id = null) {
        $task_id = intval($task_id);
        if ($task_id > 0) {
            if ($this->isExist($task_id)) {
                $reply = $this->redis->hMGet('task.'.$task_id, ['id', 'name', 'type', 'business', 'dial', 'play', 'sound', 'total', 'answer', 'complete', 'create_time']);
                $reply['id'] = $task_id;
                $reply['type'] = intval($reply['type']);
                $reply['business'] = intval($reply['business']);
                $reply['dial'] = intval($reply['dial']);
                $reply['play'] = intval($reply['play']);
                $reply['sound'] = intval($reply['sound']);
                $reply['total'] = intval($reply['total']);
                $reply['answer'] = intval($reply['answer']);
                $reply['complete'] = intval($reply['complete']);
                $reply['create_time'] = date('Y-m-d H:i:s', intval($reply['create_time']));
                $reply['remainder'] = intval($this->redis->lLen('data.' . $task_id));
                return $reply;
            }
        }
        return null;
    }

    /* start */
    public function start($company_id = null, $task_id = null) {
        $company_id = intval($company_id);
        $task_id = intval($task_id);

        if ($company_id > 0 && $task_id > 0) {
            $this->redis->hSet('company.' . $company_id, 'task', $task_id);
            return true;
        }
        return false;
    }

    /* stop */
    public function stop($company_id = null, $task_id = null) {
        $company_id = intval($company_id);
        $task_id = intval($task_id);

        if ($company_id > 0 && $task_id > 0) {
            $this->redis->hSet('company.' . $company_id, 'task', 0);
            return true;
        }
        return false;
    }

    /* delete */
    public function delete($company_id = null, $task_id = null) {
        $company_id = intval($company_id);
        $task_id = intval($task_id);

        if ($company_id > 0 && $task_id > 0) {
            if ($this->isRun($company_id, $task_id)) {
                $this->redis->hSet('company.' . $company_id, 'task', 0);
            }
            $this->redis->sRem('taskpool.' . $company_id, strval($task_id));
            $this->redis->delete('task.' . $task_id);
            $this->redis->delete('data.' . $task_id);
            return true;
        }

        return false;
    }

    /* update */
    public function update($company_id = null, $task_id = null, $data = null) {
        $company_id = intval($company_id);
        $task_id = intval($task_id);

        if ($company_id > 0 && $task_id > 0) {
            $task = $this->get($task_id);
            if ($task != null) {
                $result = null;
                switch ($task['type']) {
                case 1:
                    $result = $this->filterAuto($data);
                    break;
                case 2:
                    $result = $this->filterFixed($data);
                    break;
                case 3:
                    $result = $this->filterClick($data);
                    break;
                default:
                    break;
                }

                if ($result != null) {
                    if ($result['play'] == 1) {
                        if (!isset($result['sound'])) {
                            unset($result['play']);
                        }
                    }

                    if (isset($result['sound'])) {
                        if (!$this->checkSound($company_id, $result['sound'])) {
                            unset($result['sound']);
                        }
                    }

                    if (count($result) > 0) {
                        $this->redis->hMSet('task.' . $task_id, $result);
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /* check is run */
    public function isRun($company_id = null, $task_id = null) {
        $task_id = intval($task_id);
        $company_id = intval($company_id);

        if ($task_id > 0 && $company_id > 0) {
            $reply = $this->redis->hGet('company.' . $company_id, 'task');
            $currTask = intval($reply);
            if ($currTask === $task_id) {
                return true;
            }
        }

        return false;
    }

    /* filter of auto */
    public function filterAuto($data = null) {
        $buff = null;

        if (isset($data['name'])) {
            $name = str_replace(" ", "", $this->filter->sanitize($data['name'], 'string'));
            $len = mb_strlen($name, 'utf-8');
            if ($len > 0) {
                $buff['name'] = htmlspecialchars($name, ENT_QUOTES);
            }
        }

        if (isset($data['business'])) {
            $business = intval($data['business']);
            $allow = [1];
            if (in_array($business, $allow, true)) {
                $buff['business'] = $business;
            }
        }

        if (isset($data['dial'])) {
            $dial = intval($data['dial']);
            if ($dial > 0 && $dial <= 24) {
                $buff['dial'] = $dial;
            }
        }

        if (isset($data['sound'])) {
            $sound = intval($data['sound']);
            if ($sound > 0) {
                $buff['sound'] = $sound;
            }
        }

        if (isset($buff['sound'])) {
            $buff['play'] = 1;
        } else {
            $buff['play'] = 0;
        }

        return $buff;
    }

    /* filter of fixed */
    public function filterFixed($data = null) {
        $buff = null;

        if (isset($data['name'])) {
            $name = str_replace(" ", "", $this->filter->sanitize($data['name'], 'string'));
            $len = mb_strlen($name, 'utf-8');
            if ($len > 0) {
                $buff['name'] = htmlspecialchars($name, ENT_QUOTES);
            }
        }

        if (isset($data['business'])) {
            $business = intval($data['business']);
            $allow = [1];
            if (in_array($business, $allow, true)) {
                $buff['business'] = $business;
            }
        }

        if (isset($data['dial'])) {
            $dial = intval($data['dial']);
            if ($dial > 0 && $dial < 24) {
                $buff['dial'] = $dial;
            }
        }

        if (isset($data['sound'])) {
            $sound = intval($data['sound']);
            if ($sound > 0) {
                $buff['sound'] = $sound;
            }
        }

        if (isset($buff['sound'])) {
            $buff['play'] = 1;
        } else {
            $buff['play'] = 0;
        }
        
        return $buff;
    }

    /* filter of click */
    public function filterClick($data = null) {
        $buff = null;

        if (isset($data['name'])) {
            $name = str_replace(" ", "", $this->filter->sanitize($data['name'], 'string'));
            $len = mb_strlen($name, 'utf-8');
            if ($len > 0) {
                $buff['name'] = htmlspecialchars($name, ENT_QUOTES);
            }
        }

        if (isset($data['business'])) {
            $business = intval($data['business']);
            $allow = [1];
            if (in_array($business, $allow, true)) {
                $buff['business'] = $business;
            }
        }

        return $buff;
    }

    /* create new task */
    public function create($company_id = null, $data = null, $files = null) {
        $company_id = intval($company_id);

        if ($this->isLimitOver($company_id)) {
            return false;
        }

        if ($company_id > 0 && isset($data['type'])) {
            $type = intval($data['type']);
            $result = null;

            /* filter request data */
            if ($type == 1) {
                $result = $this->newAutoFilter($data, $files);
            } else if ($type == 2) {
                $result = $this->newFixedFilter($data, $files);
            } else if ($type == 3) {
                $result = $this->newClickFilter($data, $files);
            }

            /* create new task */
            if ($result != null) {
                if ($type == 1) {
                    $this->createAutoTask($company_id, $result);
                } else if ($type == 2) {
                    $this->createFixedTask($company_id, $result);
                } else if ($type == 3) {
                    $this->createClickTask($company_id, $result);
                }
            }
            return true;
        }

        return false;
    }

    /* auto type filter */
    public function newAutoFilter($data, $files) {
        $buff = null;
        
        if (isset($data['name'])) {
            $name = str_replace(" ", "", $this->filter->sanitize($data['name'], 'string'));
            $len = mb_strlen($name, 'utf-8');
            if ($len > 0) {
                $buff['name'] = htmlspecialchars($name, ENT_QUOTES);
            } else {
                $buff['name'] = '未命名';
            }
        } else {
            $buff['name'] = '未命名';
        }

        if (isset($data['business1'])) {
            $business = intval($data['business1']);
            $allow = [1];
            if (in_array($business, $allow, true)) {
                $buff['business'] = $business;
            } else {
                $buff['business'] = 1;
            }
        } else {
            $buff['business'] = 1;
        }

        if (isset($data['dial1'])) {
            $dial = intval($data['dial1']);
            if ($dial > 0 && $dial <= 24) {
                $buff['dial'] = $dial;
            } else {
                $buff['dial'] = 8;
            }
        } else {
            $buff['dial'] = 8;
        }

        if (isset($data['sound1'])) {
            $sound = intval($data['sound1']);
            if ($sound > 0) {
                $buff['sound'] = $sound;
            } else {
                $buff['sound'] = 0;
            }
        } else {
            $buff['sound'] = 0;
        }

        if ($buff['sound'] > 0) {
            $buff['play'] = 1;
        } else {
            $buff['play'] = 0;
        }
        
        if (isset($data['empty1'])) {
            $empty = $this->filter->sanitize($data['empty1'], 'alphanum');
            if ($empty === 'on') {
                $buff['empty'] = true;
            } else {
                $buff['empty'] = false;
            }
        } else {
            $buff['empty'] = false;
        }
        
        /* check upload file */
        if (!is_array($files)) {
            return null;
        }

        if (!isset($files['file']) && !is_array($files['file'])) {
            return null;
        }

        if ($files['file']['error'] !== 0) {
            return null;
        }
    
        $file = $files['file']['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME);
        if (!$finfo) {
            return null;
        }

        if (finfo_file($finfo, $file) !== 'text/plain; charset=us-ascii') {
            return null;
        }

        finfo_close($finfo);
    
        $size = $files['file']['size'];
        if ($size > 0 && $size < 104857700) {
            $buff['tmpFile'] = $files['file']['tmp_name'];
        } else {
            return null;
        }
    
        return $buff;
    }

    /* fixed type filter */
    public function newFixedFilter($data, $files) {
        $buff = null;
        
        if (isset($data['name'])) {
            $name = str_replace(" ", "", $this->filter->sanitize($data['name'], 'string'));
            $len = mb_strlen($name, 'utf-8');
            if ($len > 0) {
                $buff['name'] = htmlspecialchars($name, ENT_QUOTES);
            } else {
                $buff['name'] = '未命名';
            }
        } else {
            $buff['name'] = '未命名';
        }

        if (isset($data['business2'])) {
            $business = intval($data['business2']);
            $allow = [1];
            if (in_array($business, $allow, true)) {
                $buff['business'] = $business;
            } else {
                $buff['business'] = 1;
            }
        } else {
            $buff['business'] = 1;
        }

        if (isset($data['dial2'])) {
            $dial = intval($data['dial2']);
            if ($dial > 0) {
                $buff['dial'] = $dial;
            } else {
                $buff['dial'] = 50;
            }
        } else {
            $buff['dial'] = 50;
        }

        if (isset($data['sound2'])) {
            $sound = intval($data['sound2']);
            if ($sound > 0) {
                $buff['sound'] = $sound;
            } else {
                $buff['sound'] = 0;
            }
        } else {
            $buff['sound'] = 0;
        }

        if ($buff['sound'] > 0) {
            $buff['play'] = 1;
        } else {
            $buff['play'] = 0;
        }
        
        if (isset($data['empty2'])) {
            $empty = $this->filter->sanitize($data['empty2'], 'alphanum');
            if ($empty == 'on') {
                $buff['empty'] = true;
            } else {
                $buff['empty'] = false;
            }
        } else {
            $buff['empty'] = false;
        }

        /* check upload file */
        if (!is_array($files)) {
            return null;
        }

        if (!isset($files['file']) && !is_array($files['file'])) {
            return null;
        }

        if ($files['file']['error'] !== 0) {
            return null;
        }
    
        $file = $files['file']['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME);
        if (!$finfo) {
            return null;
        }

        if (finfo_file($finfo, $file) !== 'text/plain; charset=us-ascii') {
            return null;
        }

        finfo_close($finfo);
    
        $size = $files['file']['size'];
        if ($size > 0 && $size < 104857700) {
            $buff['tmpFile'] = $files['file']['tmp_name'];
        } else {
            return null;
        }
        
        return $buff;
    }

    /* click type filter */
    public function newClickFilter($data, $files) {
        $buff = null;
        
        if (isset($data['name'])) {
            $name = str_replace(" ", "", $this->filter->sanitize($data['name'], 'string'));
            $len = mb_strlen($name, 'utf-8');
            if ($len > 0) {
                $buff['name'] = htmlspecialchars($name, ENT_QUOTES);
            } else {
                $buff['name'] = '未命名';
            }
        } else {
            $buff['name'] = '未命名';
        }

        if (isset($data['business3'])) {
            $business = intval($data['business3']);
            $allow = [1];
            if (in_array($business, $allow, true)) {
                $buff['business'] = $business;
            } else {
                $buff['business'] = 1;
            }
        } else {
            $buff['business'] = 1;
        }

        /* check upload file */
        if (!is_array($files)) {
            return null;
        }

        if (!isset($files['file']) && !is_array($files['file'])) {
            return null;
        }

        if ($files['file']['error'] !== 0) {
            return null;
        }
    
        $file = $files['file']['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME);
        if (!$finfo) {
            return null;
        }

        if (finfo_file($finfo, $file) !== 'text/plain; charset=us-ascii') {
            return null;
        }

        finfo_close($finfo);
    
        $size = $files['file']['size'];
        if ($size > 0 && $size < 104857700) {
            $buff['tmpFile'] = $files['file']['tmp_name'];
        } else {
            return null;
        }
        
        return $buff;
    }

    public function the_generate_taskid() {
        $reply = $this->redis->multi()->get('counter')->incr('counter')->exec();
        $task_id = intval($reply[0]);
        if ($task_id > 0) {
            return $task_id;
        }
    
        return null;
    }


    public function checkSound($company_id = null, $sound_id = null) {
        $company_id = intval($company_id);
        $sound_id = intval($sound_id);

        if ($company_id > 0 && $sound_id > 0) {
            $reply = $this->redis->hMGet('sound.' . $sound_id, ['company', 'status']);
            $soundCompany = intval($reply['company']);
            $soundStatus = intval($reply['status']);
            if (($soundCompany === $company_id) && ($soundStatus === 1)) {
                return true;
            }
        }

        return false;
    }

    public function the_taskpool_add($company_id = null, $task_id = null) {
        $company_id = intval($company_id);
        $task_id = intval($task_id);

        if ($company_id > 0 && $task_id > 0) {
            $reply = $this->redis->sAdd('taskpool.' . $company_id, $task_id);
            if ($reply) {
                return true;
            }
        }

        return false;
    }

    public function task_file_processing($task_id = null, $checkEmpty = false, $tmpFile = null) {
        $task_id = intval($task_id);

        if ($task_id > 0) {
            if (file_exists($tmpFile)) {
                if ($checkEmpty) {
                    system('/var/www/upd -c -t ' . $task_id . ' -f ' . $tmpFile);
                } else {
                    system('/var/www/upd -t ' . $task_id . ' -f ' . $tmpFile);
                }
                return true;
            }
        }

        return false;
    }
    
    public function createAutoTask($company_id, $data) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            /* check sound */
            if ($data['play'] == 1) {
                if (!$this->checkSound($company_id, $data['sound'])) {
                    return false;
                }
            }

            /* the generate task id */
            $task_id = $this->the_generate_taskid();
            if (!$task_id) {
                return false;
            }

            $attr = ['id' => $task_id, 'name' => $data['name'], 'type' => 1, 'business' => $data['business'], 'dial' => $data['dial'], 'play' => $data['play'], 'sound' => $data['sound'], 'total' => 0, 'answer' => 0, 'complete' => 0, 'create_time' => time()];
            $this->redis->hMSet('task.' . $task_id, $attr);
            $this->the_taskpool_add($company_id, $task_id);

            /* the file processing */
            $tmpFile = str_replace('/tmp', '/dev/shm', $data['tmpFile']);
            move_uploaded_file($data['tmpFile'], $tmpFile);
            $checkEmpty = $data['empty'];
            $this->task_file_processing($task_id, $checkEmpty, $tmpFile);
            sleep(1);
            return true;
        }

        return false;
    }

    public function createFixedTask($company_id, $data) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            /* check sound */
            if ($data['play'] == 1) {
                if (!$this->checkSound($company_id, $data['sound'])) {
                    return false;
                }
            }

            /* the generate task id */
            $task_id = $this->the_generate_taskid();
            if (!$task_id) {
                return false;
            }

            /* check company concurrent */
            $sql = "SELECT * FROM company WHERE id = " . $company_id;
            $result = $this->db->fetchOne($sql);
            if ($data['dial'] > $result['concurrent']) {
                $data['dial'] = $result['concurrent'];
            }

            /* add task to pool */
            $attr = ['id' => $task_id, 'name' => $data['name'], 'type' => 2, 'business' => $data['business'], 'dial' => $data['dial'], 'play' => $data['play'], 'sound' => $data['sound'], 'total' => 0, 'answer' => 0, 'complete' => 0, 'create_time' => time()];
            $this->redis->hMSet('task.' . $task_id, $attr);
            $this->the_taskpool_add($company_id, $task_id);

            /* the file processing */
            $tmpFile = str_replace('/tmp', '/dev/shm', $data['tmpFile']);
            move_uploaded_file($data['tmpFile'], $tmpFile);
            $checkEmpty = $data['empty'];
            $this->task_file_processing($task_id, $checkEmpty, $tmpFile);
            sleep(1);
            return true;
        }
        return false;
    }

    public function createClickTask($company_id, $data) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $task_id = $this->the_generate_taskid();
            if (!$task_id) {
                return false;
            }

            $attr = ['id' => $task_id, 'name' => $data['name'], 'type' => 3, 'business' => $data['business'], 'dial' => 0, 'play' => 0, 'sound' => 0, 'total' => 0, 'answer' => 0, 'complete' => 0, 'create_time' => time()];
            $this->redis->hMSet('task.' . $task_id, $attr);
            $this->the_taskpool_add($company_id, $task_id);

            /* the file processing */
            $tmpFile = str_replace('/tmp', '/dev/shm', $data['tmpFile']);
            move_uploaded_file($data['tmpFile'], $tmpFile);
            $checkEmpty = false;
            $this->task_file_processing($task_id, $checkEmpty, $tmpFile);
            sleep(1);
            return true;
        }

        return false;
    }

    public function isLimitOver($company_id = null) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $reply = $this->redis->sCard('taskpool.' . $company_id);
            $count = intval($reply);

            if ($count < 18) {
                return false;
            }
        }

        return true;
    }
}
