<?php

class User {
    private $db = null;
    private $redis = null;
    private $cookies = null;
    private $filter = null;
    
    public function __construct ($app) {
        $this->db = $app->db;
        $this->redis = $app->redis;
        $this->cookies = $app->cookies;
        $this->filter = $app->filter;
    }

    public function isExist($uid = null) {
        $uid = $this->filter->sanitize($uid, 'alphanum');
        return $this->redis->exists('user.' . $uid);
    }

    public function isCompanyExist($company_id = null) {
        $company_id = intval($company_id);
        if ($company_id > 0) {
            return $this->redis->exists('company.' . $company_id);
        }

        return false;
    }

    public function get($uid = null) {
        $uid = $this->filter->sanitize($uid, 'alphanum');

        if ($this->isExist($uid)) {
            $sql = "SELECT * FROM users WHERE uid = " . $this->db->escapeString($uid) . " AND type = 1";
            $result = $this->db->fetchOne($sql);
            if ($result) {
                return $result;
            }
        }

        return null;
    }

    public function getAll() {
        $sql = "SELECT * FROM users WHERE type = 1 ORDER BY company ASC, create_time ASC";
        $result = $this->db->fetchAll($sql);
        if ($result) {
            return $result;
        }

        return null;
    }

    public function create($data = null) {
        $data = $this->createFilter($data);
        if ($data != null) {
            /* check uid is exist */
            if ($this->isExist($data['uid'])) {
                return false;
            }

            /* check company is exist */
            if (!$this->isCompanyExist($data['company'])) {
                return false;
            }

            $data['type'] = 1;
            $data['password'] = sha1(md5($data['password']));
            $data['icon'] = '001';
            $data['status'] = 1;
            $data['callerid'] = $data['uid'];
            $data['phone'] = 'null';
            $data['web'] = 1;
            $data['calls'] = 1;
            $data['last_login'] = '1970-01-01 08:00:00';
            $data['last_ipaddr'] = '0.0.0.0';
            $data['create_time'] = date('Y-m-d H:i:s');

            $success = $this->db->insertAsDict('users', $data);
            if ($success) {
                $this->redis->hMSet('user.' . $data['uid'], $data);
                return true;
            }
        }

        return false;
    }

    public function update($uid, $data) {
        $uid = $this->filter->sanitize($uid, 'alphanum');
        if ($this->isExist($uid)) {
            $data = $this->updateFilter($data);
            if ($data != null) {
                if (isset($data['password'])) {
                    $data['password'] = sha1(md5($data['password']));
                }
                $success = $this->db->updateAsDict('users', $data, 'uid = ' . $this->db->escapeString($uid) . ' AND type = 1');
                if ($success) {
                    $this->redis->hMSet('user.' . $uid, $data);
                    return true;
                }
            }
        }

        return false;
    }

    public function delete($uid = null) {
        $uid = $this->filter->sanitize($uid, 'alphanum');
        if ($this->isExist($uid)) {
            $this->db->delete('users', 'uid = ' . $this->db->escapeString($uid) . ' AND type = 1');
            $this->redis->delete('user.' . $uid);
            $this->redis->delete('session.' . $uid);
            return true;
        }

        return false;
    }

    public function createFilter($data) {
        $buff = null;

        if (isset($data['uid'])) {
            $uid = $this->filter->sanitize($data['uid'], 'alphanum');
            $len = mb_strlen($uid, 'utf-8');
            if ($len > 0) {
                $buff['uid'] = $uid;
            } else {
                return null;
            }
        } else {
            return null;
        }

        if (isset($data['name'])) {
            $name = str_replace(" ", "", $this->filter->sanitize($data['name'], 'string'));
            $len = mb_strlen($name, 'utf-8');
            if ($len > 0) {
                $buff['name'] = $name;
            } else {
                $buff['name'] = $buff['uid'];
            }
        } else {
            $buff['name'] = $buff['uid'];
        }

        if (isset($data['company'])) {
            $company = intval($data['company']);
            if ($company > 0) {
                $buff['company'] = $company;
            } else {
                return null;
            }
        } else {
            return null;
        }

        if (isset($data['password'])) {
            $password = $this->filter->sanitize($data['password'], 'string');
            $len = mb_strlen($password, 'utf-8');
            if ($len > 7) {
                $buff['password'] = $password;
            } else {
                return null;
            }
        } else {
            return null;
        }

        return $buff;

    }

    public function updateFilter($data = null) {
        $buff = null;

        if (isset($data['name'])) {
            $name = $this->filter->sanitize($data['name'], 'string');
            $len = mb_strlen($name, 'utf-8');
            if ($len > 0) {
                $buff['name'] = $name;
            }
        }

        if (isset($data['password'])) {
            $password = $this->filter->sanitize($data['password'], 'string');
            $len = mb_strlen($password, 'utf-8');
            if ($len > 7) {
                $buff['password'] = $password;
            }
        }

        return $buff;
    }
}
