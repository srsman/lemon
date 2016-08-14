<?php

class Agent {
    private $db = null;
    private $pbx = null;
    private $cdr = null;
    private $order = null;
    private $redis = null;
    private $filter = null;
    
    public function __construct ($app) {
        $this->db = $app->db;
        $this->pbx = $app->pbx;
        $this->cdr = $app->cdr;
        $this->order = $app->order;
        $this->redis = $app->redis;
        $this->filter = $app->filter;
    }

    public function getTodayOrder($uid = null) {
        $uid = intval($uid);

        if ($uid > 1000) {
            $start = date('Y-m-d 08:00:00');
            $end = date('Y-m-d 20:00:00');
            $sql = "SELECT * FROM orders WHERE creator = '" . $uid . "' AND create_time BETWEEN '" . $start . "' AND '" . $end . "' ORDER BY id";
            $result = $this->order->fetchAll($sql);
            if ($result && count($result) > 0) {
                return $result;
            }
        }

        return null;
    }

    public function getOrder($uid = null, $id = null) {
        $uid = intval($uid);

        if ($uid > 1000) {
            $sql = "SELECT * FROM orders WHERE creator = '" . $uid . "' ORDER BY create_time DESC LIMIT 25";

            $id = intval($id);
            if ($id > 0) {
                $sql = "SELECT * FROM orders WHERE creator = '" . $uid . "' AND id < " . $id . " ORDER BY create_time DESC LIMIT 25";
            }

            $result = $this->order->fetchAll($sql);
            if ($result && count($result) > 0) {
                return $result;
            }
        }

        return null;
    }

    public function getStatus($uid) {
        $uid = intval($uid);

        if ($uid > 1000) {
            $sql = "SELECT status, state FROM agents WHERE name = '" . $uid . "'";
            $result = $this->pbx->fetchOne($sql);
            if ($result && count($result) > 0) {
                return $result;
            }
        }

        return null;
    }

    public function filter($data) {
        $buff = null;
        
        if (isset($data['name'])) {
            $name = str_replace(" ", "", strip_tags($data['name']));
            $len = mb_strlen($name, 'utf-8');
            if ($len > 0 && $len <= 12) {
                $buff['name'] = htmlspecialchars($name, ENT_QUOTES);
            }
        }

        if (isset($data['password'])) {
            $password = str_replace(" ", "", strip_tags($this->filter->sanitize($data['password'], 'string')));
            $len = mb_strlen($password, 'utf-8');
            if ($len > 7 && $len < 32) {
                $buff['password'] = htmlspecialchars($password, ENT_QUOTES);
            }
        }
        
        if (isset($data['type'])) {
            $type = intval($data['type']);
            $allow = [1, 2, 3];
            if (in_array($type, $allow, true)) {
                $buff['type'] = $type;
            }
        }

        if (isset($data['phone'])) {
            $phone = $this->filter->sanitize($data['phone'], 'alphanum');
            $len = mb_strlen($phone, 'utf-8');
            if ($len > 10 && $len < 15) {
                $buff['phone'] = $phone;
            }
        }
        
        if (isset($data['callerid'])) {
            $callerid = $this->filter->sanitize($data['callerid'], 'alphanum');
            $len = mb_strlen($callerid, 'utf-8');
            if ($len > 0 && $len < 18) {
                $buff['callerid'] = $callerid;
            }
        }

        if (isset($data['icon'])) {
            $icon = $this->filter->sanitize($data['icon'], 'alphanum');
            $allow = ['001', '002', '003', '004', '005', '006', '007',
                      '008', '009', '010', '011', '012', '013', '014',
                      '100', '101', '102', '103', '104', '105', '106',
                      '107', '108', '109', '110'];

            if (in_array($icon, $allow, true)) {
                $buff['icon'] = $icon;
            }
        }

        if (isset($data['status'])) {
            $status = intval($data['status']);
            $allow = [0, 1];
            if (in_array($status, $allow, true)) {
                $buff['status'] = $status;
            }
        }

        if (isset($data['calls'])) {
            $calls = $data['calls'];
            $allow = [true, false];
            if (in_array($calls, $allow, true)) {
                $buff['calls'] = $calls ? 1 : 0;
            }
        }

        if (isset($data['web'])) {
            $web = $data['web'];
            $allow = [true, false];
            if (in_array($web, $allow, true)) {
                $buff['web'] = $web ? 1 : 0;
            }
        }

        return $buff;
    }

    public function getCurrCalled($uid = null) {
        $sql = "SELECT cid_num, callee_num FROM basic_calls WHERE cid_num = '" . $uid . "' OR callee_num = '" . $uid . "'";
        $result = $this->pbx->fetchOne($sql);
        if ($result && count($result) > 0) {
            if ($result['cid_num'] == $uid) {
                return $result['callee_num'];
            }
            return $result['cid_num'];
        }

        $table = 'cdr_' . date('Ym');
        $sql = "SELECT caller, callee FROM " . $table . " WHERE caller = '" . $uid . "' OR callee = '" . $uid . "' ORDER BY create_time DESC";
        $result = $this->cdr->fetchOne($sql);
        if ($result && count($result) > 0) {
            if ($result['caller'] == $uid) {
                return $result['callee'];
            }
            return $result['caller'];
        }

        return '';
    }
}
