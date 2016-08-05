<?php

class Gateway {
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
    
    public function get($company_id = null, $esl = null) {
        $company_id = intval($company_id);

        if ($company_id > 0 && $this->isCompanyExist($company_id)) {
            $sql = "SELECT * FROM gateway WHERE company = " . $company_id;
            $result = $this->db->fetchOne($sql);
            if ($result && count($result) > 0) {
                if ($esl) {
                    $result['status'] = $this->sofia_status_gateway($esl, $company_id);
                } else {
                    $result['status'] = null;
                }

                return $result;
            }
        }

        return null;
    }

    public function getAll($esl = null) {
        $gateways = null;

        $sql = "SELECT id FROM company ORDER BY id";
        $result = $this->db->fetchAll($sql);
        if ($result && count($result) > 0) {
            foreach ($result as $company) {
                $gateway = $this->get($company['id'], $esl);
                if ($gateway != null) {
                    $gateways[] = $gateway;
                } 
            }
        }

        return $gateways;
    }

    public function update($esl = null, $company_id = null, $data = null) {
        $company_id = intval($company_id);

        if ($esl && $company_id > 0 && $this->isCompanyExist($company_id)) {
            $data = $this->updateFilter($data);
            if ($data != null) {
                $gateway = $this->get($company_id);
                if ($gateway == null) {
                    return false;
                }

                if (isset($data['username']) && $gateway['username'] == $data['username']) {
                    unset($data['username']);
                }

                if (isset($data['password']) && $gateway['password'] == $data['password']) {
                    unset($data['password']);
                }

                if (isset($data['ip_addr']) && $gateway['ip_addr'] == $data['ip_addr']) {
                    unset($data['ip_addr']);
                }

                if (count($data) > 0) {
                    $this->db->updateAsDict('gateway', $data, 'company = ' . $company_id);
                    $this->syncPbx($esl, $company_id);
                }

                return true;
            }
        }

        return false;
    }

    public function sofia_status_gateway($esl = null, $company_id = null) {
        $company_id = intval($company_id);

        if ($esl && $company_id > 0 && $this->isCompanyExist($company_id)) {
            $e = $esl->sendRecv('api sofia xmlstatus gateway trunk.' . $company_id . '.gw');
            return simplexml_load_string($e->getBody());
        }
        
        return null;
    }

    public function updateFilter($data = null) {
        $buff = null;

        if (isset($data['username'])) {
            $username = $this->filter->sanitize($data['username'], 'alphanum');
            $len = mb_strlen($username, 'utf-8');
            if ($len > 0) {
                $buff['username'] = $username;
            }
        }

        if (isset($data['password'])) {
            $password = $this->filter->sanitize($data['password'], 'alphanum');
            $len = mb_strlen($password, 'utf-8');
            if ($len > 7) {
                $buff['password'] = $password;
            }
        }

        if (isset($data['ipaddr'])) {
            $ipaddr = str_replace(" ", "", $this->filter->sanitize($data['ipaddr'], 'string'));
            $len = mb_strlen($ipaddr, 'utf-8');
            if ($len > 0) {
                $buff['ip_addr'] = $ipaddr;
            }
        }

        return $buff;
    }

    public function syncPbx($esl = null, $company_id = null) {
        $company_id = intval($company_id);

        if ($esl && $company_id > 0 && $this->isCompanyExist($company_id)) {
            $gateway = $this->get($company_id);
            if ($gateway != null) {
                $this->fsWriteGateway($esl, $company_id, $gateway['username'], $gateway['password'], $gateway['ip_addr']);
                return true;
            }

            return false;
        }
    }

    public function fsWriteGateway($esl = null, $company_id = null, $username = null, $password = null, $ipaddr = null) {
        $company_id = intval($company_id);

        if ($esl && $company_id > 0) {
            $this->fsWriteXmlGateway($company_id, $username, $password, $ipaddr);
            sleep(1);
            $esl->send('bgapi sofia profile external killgw trunk.' . $company_id . '.gw');
            sleep(1);
            $esl->send('bgapi sofia profile external rescan');
            sleep(1);
            return true;
        }


        return false;
    }

    public function fsWriteXmlGateway($company_id = null, $username = null, $password = null, $ipaddr = null) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $xml = "<include>\n";
            $xml .= "  <gateway name=\"trunk.".$company_id.".gw\">\n";
            $xml .= "    <param name=\"username\" value=\"".$username."\"/>\n";
            $xml .= "    <param name=\"password\" value=\"".$password."\"/>\n";
            $xml .= "    <param name=\"realm\" value=\"".$ipaddr."\"/>\n";
            $xml .= "    <param name=\"proxy\" value=\"".$ipaddr."\"/>\n";
            $xml .= "    <param name=\"register\" value=\"true\"/>\n";
            $xml .= "  </gateway>\n";
            $xml .= "</include>\n";

            $file = '/usr/local/freeswitch/conf/sip_profiles/external/trunk.' . $company_id . '.xml';
            $fp = fopen($file, "w");
            if ($fp) {
                fwrite($fp, $xml);
                fclose($fp);
                return true;
            }
        }

        return false;
    }
}
