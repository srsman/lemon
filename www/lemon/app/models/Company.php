<?php

class Company {
    private $db = null;
    private $pbx = null;
    private $order = null;
    private $redis = null;
    private $radius = null;
    private $filter = null;
    
    public function __construct($app) {
        $this->db = $app->db;
        $this->pbx = $app->pbx;
        $this->order = $app->order;
        $this->redis = $app->redis;
        $this->radius = $app->radius;
        $this->filter = $app->filter;
    }
    
    public function isExist($company_id) {
        $company_id = intval($company_id);
        if ($company_id > 0) {
            return $this->redis->exists('company.' . $company_id);
        }
        return false;
    }

    public function get($company_id = null) {
        $company_id = intval($company_id);
        if ($company_id > 0) {
            $sql = "SELECT * FROM company WHERE id = " . $company_id;
            $result = $this->db->fetchOne($sql);
            return $result;
        }
        return null;
    }

    public function get_all_user($company_id = null) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            if ($this->isExist($company_id)) {
                $sql = "SELECT * FROM users WHERE type in(2, 3) AND company = {$company_id} ORDER BY uid";
                $result = $this->db->fetchAll($sql);
                return $result;
            }
        }

        return null;
    }

    public function getAgentTotal($company_id = null) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $sql = "SELECT count(uid) FROM users WHERE type in(2, 3) AND company = " . $company_id;
            $result = $this->db->fetchOne($sql);
            return intval($result['count']);
        }

        return 0;
    }

    public function get_all_exten($company_id) {
        $company_id = intval($company_id);
        if ($this->isExist($company_id)) {
            $sql = 'SELECT uid, name FROM users WHERE type in(2, 3) AND company = ' . $company_id . ' ORDER BY uid';
            $agents = $this->db->fetchAll($sql);
            $extens = null;
            foreach($agents as $agent) {
                $sql = "SELECT sip_user, ping_status, network_ip, network_port, user_agent FROM sip_registrations WHERE sip_user = '{$agent['uid']}'";
                $result = $this->pbx->fetchOne($sql);
                if ($result) {
                    $result['name'] = $agent['name'];
                } else {
                    $result = ['sip_user' => $agent['uid'], 'name' => $agent['name'],'ping_status' => 'Unregistered', 'network_ip' => '0.0.0.0', 'network_port' => 'null', 'user_agent' =>  'Unknown Device Name'];
                }
                $extens[] = $result;
            } 
            return $extens;
        }

        return null;
    }

    public function getBilling($company_id = null) {
        $company_id = intval($company_id);
        $billing = ['money' => 0.00, 'limitmoney' => 0.00, 'todayconsumption' => 0.00];

        if ($company_id > 0) {
            $company = $this->get($company_id);
            if ($company != null) {
                $account = $this->radius->hMGet($company['billing'], ['money', 'limitmoney', 'todayconsumption']);
                $billing['money'] = $account['money'];
                $billing['limitmoney'] = $account['limitmoney'];
                $billing['todayconsumption'] = $account['todayconsumption'];
            } 
        }

        return $billing;
    }

    public function getProduct($company_id) {
        $company_id = intval($company_id);
        if ($company_id > 0 && $this->isExist($company_id)) {
            $sql = 'SELECT * FROM product WHERE company = ' . $company_id . ' ORDER BY id';
            $result = $this->db->fetchAll($sql);
            if ($result && count($result) > 0) {
                return $result;
            }        
        }
        return null;
    }

    public function getSounds($company_id) {
        $company_id = intval($company_id);

        if ($this->isExist($company_id)) {
            $sql = 'SELECT * FROM sounds WHERE company = ' . $company_id . ' ORDER BY id';
            $result = $this->db->fetchAll($sql);
            return $result; 
        }

        return null;
    }

    public function getPassSounds($company_id) {
        $company_id = intval($company_id);

        if ($this->isExist($company_id)) {
            $sql = 'SELECT * FROM sounds WHERE company = ' . $company_id . ' AND status = 1 ORDER BY id';
            $result = $this->db->fetchAll($sql);
            return $result; 
        }

        return null;
    }

    public function getOrder($company_id, $where) {
        $company_id = intval($company_id);
        if ($company_id > 0) {
            if ($this->isExist($company_id)) {
                $where = $this->whereFilter($where);
                if ($where != null) {
                    $sql = "SELECT * FROM orders WHERE company = " . $company_id;

                    if (isset($where['id'])) {
                        $sql .= " AND id = " . $where['id'];
                        goto END;
                    }

                    if (isset($where['creator'])) {
                        $company = $this->redis->hGet('agent.' . $where['creator'], 'company');
                        $company = intval($company);
                        if ($company > 0) {
                            if ($company === $company_id) {
                                $sql .= " AND creator = '" . $where['creator'] . "'";
                            }
                        }
                    }

                    if (isset($where['status'])) {
                        $sql .= " AND status = " . $where['status'];
                    }


                    if ($where['sort'] == 1) {
                        $sql .= " AND create_time BETWEEN '" . $where['start'] . "' AND '" . $where['end'] . "' ORDER BY create_time";
                    } else {
                        $sql .= " AND quality_time BETWEEN '" . $where['start'] . "' AND '" . $where['end'] . "' ORDER BY quality_time";
                    }

                    END:
                    return $this->order->fetchAll($sql);
                }
            }
        }

        return null;
    }

    public function whereFilter($where) {
        $buff = null;

        if (isset($where['id'])) {
            $id = intval($where['id']);
            if ($id > 0) {
                $buff['id'] = $id;
            }
        }

        if (isset($where['creator'])) {
            $creator = $this->filter->sanitize($where['creator'], 'alphanum');
            $len = mb_strlen($creator, 'utf-8');
            if ($len > 0) {
                $buff['creator'] = $creator;
            }
        }

        if (isset($where['status'])) {
            $status = intval($where['status']);
            $allow = [1, 2, 3, 4, 5];
            if (in_array($status, $allow, true)) {
                $buff['status'] = $status;
            }
        }

        if (isset($where['start'])) {
            $start = $this->filter->sanitize($where['start'], 'string');
            if ($this->is_date($start)) {
                $buff['start'] = $start;
            } else {
                $buff['start'] = date('Y-m-d 08:00:00', time());
            }
        } else {
            $buff['start'] = date('Y-m-d 08:00:00', time());
        }

        if (isset($where['end'])) {
            $end = $this->filter->sanitize($where['end'], 'string');
            if ($this->is_date($end)) {
                $buff['end'] = $end;
            } else {
                $buff['end'] = date('Y-m-d 20:00:00', time());
            }
        } else {
            $buff['end'] = date('Y-m-d 20:00:00', time());
        }

        if (isset($where['sort'])) {
            $sort = intval($where['sort']);
            $allow = [1, 2];
            if (in_array($sort, $allow, true)) {
                $buff['sort'] = $sort;
            } else {
                $buff['sort'] = 1;
            }
        } else {
            $buff['sort'] = 1;
        }

        if (isset($where['export'])) {
            $export = $this->filter->sanitize($where['export'], 'alphanum');
            if ($export === 'on') {
                $buff['export'] = true;
            }
        }

        return $buff;
    }

    public function getTaskPool($company_id = null) {
        $company_id = intval($company_id);
        if ($company_id > 0) {
            $reply = $this->redis->sMembers('taskpool.' . $company_id);
            if (is_array($reply) && count($reply) > 0) {
                return $reply;
            }
        }
        return null;
    }

    private function is_date($date, $format = 'Y-m-d H:i:s') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }
}