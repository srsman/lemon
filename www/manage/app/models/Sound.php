<?php

class Sound {
    private $db = null;
    private $redis = null;
    private $filter = null;
    
    public function __construct ($app) {
        $this->db = $app->db;
        $this->redis = $app->redis;
        $this->filter = $app->filter;
    }

    public function isExist($sound_id = null) {
        $sound_id = intval($sound_id);
        if ($sound_id > 0) {
            return $this->redis->exists('sound.' . $sound_id);
        }
        return false;
    }

    public function isCompanyExist($company_id = null) {
        $company_id = intval($company_id);
        if ($company_id > 0) {
            return $this->redis->exists('company.' . $company_id);
        }

        return false;
    }
    
    public function get($sound_id = null) {
        $sound_id = intval($sound_id);

        if ($sound_id > 0) {
            if ($this->isExist($sound_id)) {
                $sql = "SELECT * FROM sounds WHERE id = " . $sound_id;
                $result = $this->db->fetchOne($sql);
                if ($result && count($result) > 0) {
                    return $result;
                }
            }
        }

        return null;
    }

    public function getAll() {
        $sql = "SELECT * FROM sounds ORDER BY id";
        $result = $this->db->fetchAll($sql);
        if ($result && count($result) > 0) {
            return $result;
        }

        return null;
    }

    public function pass($sound_id = null) {
        $sound_id = intval($sound_id);

        if ($sound_id > 0 && $this->isExist($sound_id)) {
            $sql = "UPDATE sounds SET status = 1 WHERE id = " . $sound_id;
            $this->db->query($sql);
            $this->redis->hSet('sound.' . $sound_id, 'status', '1');
            return true;
        }

        return false;
    }

    public function reject($sound_id = null) {
        $sound_id = intval($sound_id);

        if ($sound_id > 0 && $this->isExist($sound_id)) {
            $sql = "UPDATE sounds SET status = -1 WHERE id = " . $sound_id;
            $this->db->query($sql);
            $this->redis->hSet('sound.' . $sound_id, 'status', '-1');
            return true;
        }

        return false;
    }

    public function upload($uid = null, $data = null, $files = null) {
        if ($uid == null || $data == null || $files == null) {
            return false;
        }

        $company_id = 0;

        $data = $this->checkUpload($data, $files);
        if ($data != null) {
            $file = uniqid().'.wav';
            $path = '/var/www/lemon/public/sounds/' . $file;

            if (move_uploaded_file($data['tmpFile'], $path)) {
                $data['file'] = $file;
                $data['company'] = $company_id;
                $data['duration'] = 0;
                $data['status'] = 0;
                $data['operator'] = $uid;
                $data['create_time'] = date('Y-m-d H:i:s', time());
                $data['ip_addr'] = $_SERVER["REMOTE_ADDR"];
                unset($data['tmpFile']);

                $success = $this->db->insertAsDict('sounds', $data);
                if ($success) {
                    $sql = "SELECT last_value FROM sounds_id_seq WHERE sequence_name = 'sounds_id_seq'";
                    $result = $this->db->fetchOne($sql);
                    if ($result) {
                        $sound_id = intval($result['last_value']);
                        if ($sound_id > 0) {
                            $this->redis->hMSet('sound.' . $sound_id, $data);
                            return true;
                        }
                    }
                    return true;
                }
                unlink($path);
            }
        }

        return false;
    }

    public function delete($sound_id = null) {
        $sound_id = intval($sound_id);

        if ($sound_id > 0 && $this->isExist($sound_id)) {
            $sound = $this->get($sound_id);
            $file = '/var/www/lemon/public/sounds/' . $sound['file'];
            if (file_exists($file)) {
                unlink($file);
            }

            $this->db->delete('sounds', 'id = ' . $sound_id);
            $this->redis->delete('sound.' . $sound_id);

            return true;
        }

        return false;
    }

    public function update($sound_id = null, $data = null, $files = null) {
        $sound_id = intval($sound_id);

        if ($sound_id > 0 && $this->isExist($sound_id)) {
            $data = $this->updateFilter($data, $files);

            if ($data != null) {
                if (isset($data['tmpFile'])) {
                    $sound = $this->get($sound_id);
                    if ($sound != null) {
                        $path = '/var/www/lemon/public/sounds/' . $sound['file'];
                        move_uploaded_file($data['tmpFile'], $path);
                    }
                    unset($data['tmpFile']);
                }

                if (count($data) > 0) {
                    $this->db->updateAsDict('sounds', $data, 'id = ' . $sound_id);
                    $this->redis->hMSet('sound.' . $sound_id, $data);
                }

                return true;
            }
        }

        return false;
    }
    
    public function checkUpload($data, $files) {
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

        // check upload file
        if (!isset($files['file']) && !is_array($files['file'])) {
            return null;
        }

        if ($files['file']['error'] !== 0) {
            return null;
        }
    
        $file = $files['file']['tmp_name'];
        $finfo = finfo_open (FILEINFO_MIME);
        if (!$finfo) {
            return null;
        }

        if (finfo_file($finfo, $file) !== 'audio/x-wav; charset=binary') {
            return null;
        }

        finfo_close($finfo);
    
        $size = $files['file']['size'];
        /* sound file max size < 2MB */
        if ($size > 0 && $size < 2097152) {
            $buff['tmpFile'] = $files['file']['tmp_name'];
        } else {
            return null;
        }

        if (isset($data['remark'])) {
            $remark = str_replace(" ", "", $this->filter->sanitize($data['remark'], 'string'));
            $len = mb_strlen($remark, 'utf-8');
            if ($len > 0) {
                $buff['remark'] = htmlspecialchars($remark, ENT_QUOTES);
            } else if ($defval) {
                $buff['remark'] = 'no description';
            }
        } else {
            $buff['remark'] = 'no description';
        }

        return $buff;
    }

    public function updateFilter($data = null, $files = null) {
        $buff = null;

        if (isset($data['name'])) {
            $name = str_replace(" ", "", $this->filter->sanitize($data['name'], 'string'));
            $len = mb_strlen($name, 'utf-8');
            if ($len > 0) {
                $buff['name'] = htmlspecialchars($name, ENT_QUOTES);
            }
        }

        if (isset($data['company'])) {
            $company = intval($data['company']);
            if ($company > 0 && $this->isCompanyExist($company)) {
                $buff['company'] = $company;
            }
        }
        
        // check upload file
        if (isset($files['file']) && is_array($files['file'])) {
            if ($files['file']['error'] == 0) {
                $file = $files['file']['tmp_name'];
                $finfo = finfo_open (FILEINFO_MIME);
                if ($finfo) {
                    if (finfo_file($finfo, $file) == 'audio/x-wav; charset=binary') {
                        finfo_close($finfo);
    
                        $size = $files['file']['size'];
                        /* sound file max size < 2MB */
                        if ($size > 0 && $size < 2097152) {
                            $buff['tmpFile'] = $files['file']['tmp_name'];
                        }
                    }
                }
            }
        }

        if (isset($data['remark'])) {
            $remark = str_replace(" ", "", $this->filter->sanitize($data['remark'], 'string'));
            $len = mb_strlen($remark, 'utf-8');
            if ($len > 0) {
                $buff['remark'] = htmlspecialchars($remark, ENT_QUOTES);
            }
        }

        return $buff;
    }
}
