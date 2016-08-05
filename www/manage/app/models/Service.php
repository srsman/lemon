<?php

class Service {
    private $db = null;
    private $redis = null;
    private $filter = null;
    
    public function __construct ($app) {
        $this->db = $app->db;
        $this->redis = $app->redis;
        $this->filter = $app->filter;
    }

    public function isCompanyExist($company_id = null) {
        $company_id = intval($company_id);
        if ($company_id > 0) {
            return $this->redis->exists('company.' . $company_id);
        }

        return false;
    }

    public function get($company_id = null) {
        $company_id = intval($company_id);

        if ($company_id > 0 && $this->isCompanyExist($company_id)) {
            $service['id'] = $company_id;
            $service['name'] = $this->redis->hGet('company.' . $company_id, 'name');
            $service['status'] = $this->isRun($company_id);
            $service['pid'] = $this->getPid($company_id);
            $service['create_time'] = $this->redis->hGet('company.' . $company_id, 'create_time');
            return $service;
        }

        return false;
    }

    public function getAll() {
        $services = null;
        $sql = "SELECT id FROM company ORDER BY id";
        $result = $this->db->fetchAll($sql);
        if ($result && count($result) > 0) {
            foreach ($result as $company) {
                $service = $this->get($company['id']);
                if ($service != null) {
                    $services[] = $service;
                }
            }
        }

        return $services;
    }

    public function isRun($company_id = null) {
        $company_id =intval($company_id);

        if ($company_id > 0) {
            $pid = $this->getPid($company_id);
            if ($pid > 0) {
                $dir = '/proc/' . $pid;
                if(is_dir($dir)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getPid($company_id = null) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $file = '/var/service/' . $company_id .'.pid';
            if (file_exists($file)) {
                $fp = fopen($file ,  "r" );
                if ($fp) {
                    $pid = fgets($fp, 4096);
                    if ($pid !== false) {
                        return intval($pid);
                    }
                    fclose($fp);
                }
            }
        }

        return 0;
    }

    public function start($company_id = null) {
        $company_id = intval($company_id);

        if ($company_id > 0 && $this->isCompanyExist($company_id)) {
            $cmd = '/usr/bin/robot -d -f /etc/config.conf -c ' . $company_id;
            system($cmd);
            return true;
        }

        return false;
    }

    public function stop($company_id = null) {
        $company_id = intval($company_id);

        if ($company_id > 0 && $this->isCompanyExist($company_id)) {
            if ($this->isRun($company_id)) {
                $pid = $this->getPid($company_id);
                if ($pid > 0) {
                    system('/usr/bin/kill -9 ' . $pid);
                    return true;
                }
            }
        }

        return false;
    }
}
