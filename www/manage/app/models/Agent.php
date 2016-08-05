<?php

class Agent {
    private $db = null;
    private $redis = null;
    private $filter = null;
    
    public function __construct ($app) {
        $this->db = $app->db;
        $this->redis = $app->redis;
        $this->cookies = $app->cookies;
        $this->filter = $app->filter;
    }

    public function isExist($uid = null) {
        $uid = intval($uid);
        if ($uid > 1000) {
            return $this->redis->exists('user.' . $uid);
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

    public function get($uid = null) {
        $uid = intval($uid);

        if ($uid > 1000 && $this->isExist($uid)) {
            $attr = ['uid', 'name', 'password', 'type', 'icon', 'company', 'callerid', 'status', 'phone', 'web', 'calls', 'last_login', 'last_ipaddr', 'create_time'];
            $reply = $this->redis->hMGet('user.' . $uid, $attr);
            $type = intval($reply['type']);
            if (in_array($type, [2 , 3], true)) {
                return $reply;
            }
        }

        return null;
    }

    public function getAll($company_id = null) {
        if ($company_id != null) {
            $company_id = intval($company_id);
            if ($company_id > 0) {
                $sql = "SELECT * FROM users WHERE type in(2, 3) AND company = " . $company_id . "ORDER BY uid";
                $result = $this->db->fetchAll($sql);
                if ($result) {
                    return $result;
                }
            }

            return null;
        }

        $sql = "SELECT * FROM users WHERE type in(2, 3) ORDER BY company ASC, uid ASC";
        $result = $this->db->fetchAll($sql);
        if ($result) {
            return $result;
        }

        return null;
    }

    public function create($uid = null, $company_id = null, $password = null) {
        $company_id = intval($company_id);
        
        /* check uid is exist */
        if ($this->isExist($uid)) {
            return false;
        }

        if ($company_id > 0) {
            /* check company is exist */
            if (!$this->isCompanyExist($company_id)) {
                return false;
            }

            /* check password */
            if (!$password || mb_strlen($password, 'utf-8') < 8) {
                return false;
            }
            
            /* agent type */
            $attr['uid'] = strval($uid);
            $attr['type'] = 3;
            $attr['name'] = strval($uid);
            $attr['password'] = $password;
            $attr['company'] = $company_id;
            $attr['icon'] = '001';
            $attr['status'] = 1;
            $attr['callerid'] = strval($uid);
            $attr['phone'] = 'null';
            $attr['web'] = 1;
            $attr['calls'] = 1;
            $attr['last_login'] = '1970-01-01 08:00:00';
            $attr['last_ipaddr'] = '0.0.0.0';
            $attr['create_time'] = date('Y-m-d H:i:s');

            $success = $this->db->insertAsDict('users', $attr);
            if ($success) {
                $this->redis->hMSet('user.' . $uid, $attr);
                return true;
            }
        }

        return false;
    }

    public function batchCreate($company_id = null, $start = null, $password = null, $count = 1) {
        $company_id = intval($company_id);
        $start = intval($start);
        $count = intval($count);

        if ($start < 1001 || $count < 1) {
            return false;
        }

        if ($company_id > 0) {
            /* check company is exist */
            if (!$this->isCompanyExist($company_id)) {
                return false;
            }

            /* check password */
            if (!$password || mb_strlen($password, 'utf-8') < 8) {
                return false;
            }
            
            $end = $start + $count;
            for (;$start < $end; $start++) {
                $this->create($start, $company_id, $password);
            }

            $esl = new ESLconnection(ESL_HOST, ESL_PORT, ESL_PASSWORD);

            /* sync agent database to freeswitch */
            $this->syncPbx($esl, $company_id);

            if ($esl) {
                $esl->disconnect();
            }

            return true; 
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

    public function batchUpdate($company_id = null, $data = null) {
        $company_id = intval($company_id);

        if ($company_id > 0 && $data != null && $this->isCompanyExist($company_id)) {
            $data = $this->batchUpdateFilter($data);
            if ($data == null) {
                return false;
            }

            $attr = null;

            if ($data['password']) {
                $attr['password'] = $data['password'];
            }

            if ($data['type']) {
                $attr['type'] = 3;
            }

            if ($data['name']) {
                $attr['name'] = 'null';
            }

            if ($data['icon']) {
                $attr['icon'] = '001';
            }

            $attr['status'] = 1;
            $attr['web'] = $data['web'];
            $attr['calls'] = $data['calls'];
            $attr['last_login'] = '1970-01-01 08:00:00';
            $attr['last_ipaddr'] = '0.0.0.0';

            if ($attr == null) {
                return false;
            }

            $success = $this->db->updateAsDict('users', $attr, 'company = ' . $company_id . ' AND type in(2, 3)');
            if ($success) {
                $result = $this->getAll($company_id);
                if ($result && count($result) > 0) {
                    foreach ($result as $agent) {
                        $this->redis->hMSet('user.' . $agent['uid'], $agent);
                    }

                    if (isset($attr['password'])) {
                        foreach ($result as $agent) {
                            $this->fsWriteDirectory($company_id, $agent);
                        }

                        $esl = new ESLconnection(ESL_HOST, ESL_PORT, ESL_PASSWORD);

                        $this->reloadxml($esl);
                        sleep(5);
                        if ($esl) {
                            $esl->disconnect();
                        }
                    }

                    return true;
                }
            }
        }

        return false;
    }

    public function delete($uid = null) {
        $uid = intval($uid);

        if ($uid > 1000) {
            if ($this->isExist($uid)) {

                $esl = new ESLconnection(ESL_HOST, ESL_PORT, ESL_PASSWORD);
                
                unlink('/usr/local/freeswitch/conf/directory/default/' . $uid . '.xml');
                
                $this->sofia_exten_unregister($esl, $uid);
                $this->sofia_rescan($esl);
                $this->callcenter_agent_logout($esl, $uid);
                $this->callcenter_agent_delete($esl, $uid);
                $this->db->delete('users', "uid = '" . $uid . "' AND type in(2 ,3)");

                $result = $this->get($uid);
                if ($result != null) {
                    $company_id = intval($result['company']);
                    $this->callcenter_tier_delete($esl, $company_id, $uid);
                }

                $result = $this->getAll($company_id);
                if ($result != null && count($result) > 0) {
                    $this->fsWriteAgent($company_id, $result);
                    $this->fsWriteTiers($company_id, $result);
                }
                
                if ($esl) {
                    $esl->disconnect();
                }

                $this->redis->delete('user.' . $uid);
                $this->redis->delete('session.' . $uid);

                return true;
            }
        }

        return false;
    }
    
    public function syncPbx($esl, $company_id = null) {
        $company_id = intval($company_id);

        if ($company_id > 0 && $this->isCompanyExist($company_id)) {
            $sql = "SELECT * FROM users WHERE type in(2, 3) AND company = " . $company_id . " ORDER BY uid";
            $result= $this->db->fetchAll($sql);
            if ($result && count($result) > 0) {
                foreach ($result as $agent) {
                    $this->fsWriteDirectory($company_id, $agent);
                }

                $this->fsWriteAgent($company_id, $result);

                $this->fsWriteTiers($company_id, $result);

                $this->reloadxml($esl);
                sleep(5);
                $this->callcenter_config_queue_load($esl, $company_id);
                $this->callcenter_config_agent_reload($esl, $company_id, $result);
                $this->callcenter_config_tier_reload($esl, $company_id, $result);
                
                return true;
            }
        }

        return false;
    }

    public function fsWriteDirectory($company_id = null, $agent = null) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $xml = "<include>\n";
            $xml .= "  <user id=\"" . $agent['uid'] . "\">\n";
            $xml .= "    <params>\n";
            $xml .= "      <param name=\"password\" value=\"" . $agent['password'] . "\"/>\n";
            $xml .= "    </params>\n";
            $xml .= "    <variables>\n";
            $xml .= "      <variable name=\"toll_allow\" value=\"domestic,international,local\"/>\n";
            $xml .= "      <variable name=\"accountcode\" value=\"" . $agent['company'] . "\"/>\n";
            $xml .= "      <variable name=\"user_context\" value=\"default\"/>\n";
            $xml .= "      <variable name=\"effective_caller_id_name\" value=\"Extension " . $agent['uid'] . "\"/>\n";
            $xml .= "      <variable name=\"effective_caller_id_number\" value=\"" . $agent['uid'] . "\"/>\n";
            $xml .= "      <variable name=\"outbound_caller_id_name\" value=\"" . $agent['callerid'] . "\"/>\n";
            $xml .= "      <variable name=\"outbound_caller_id_number\" value=\"" . $agent['callerid'] . "\"/>\n";
            $xml .= "      <variable name=\"callgroup\" value=\"techsupport\"/>\n";
            $xml .= "    </variables>\n";
            $xml .= "  </user>\n";
            $xml .= "</include>\n";

            $file = '/usr/local/freeswitch/conf/directory/default/' . $agent['uid'] . '.xml';
            $fp = fopen($file, "w");
            if ($fp) {
                fwrite($fp, $xml);
                fclose($fp);
            }

            return true;
        }

        return false;
    }

    public function fsWriteAgent($company_id = null, $agents = null) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $xml = "<include>\n";
            foreach ($agents as $agent) {
                $xml .= '  <agent name="' . $agent['uid'] . '" type="callback" contact="user/' . $agent['uid'] . '" status="Logged Out" max-no-answer="24" wrap-up-time="5" reject-delay-time="0" busy-delay-time="5"'." />\n";
            }
            $xml .= "</include>\n";

            $file = '/usr/local/freeswitch/conf/agents/agent.' . $company_id . '.xml';
            $fp = fopen($file, "w");
            if ($fp) {
                fwrite($fp, $xml);
                fclose($fp);
            }

            return true;
        }

        return false;
    }

    public function fsWriteTiers($company_id = null, $agents = null) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $xml = "<include>\n";
            foreach ($agents as $agent) {
                $xml .= '  <tier agent="' . $agent['uid'] . '" queue="' . $company_id . '@queue" level="1" position="1"/>'."\n";
            }
            $xml .= "</include>\n";

            $file = '/usr/local/freeswitch/conf/tiers/tiers.' . $company_id . '.xml';
            $fp = fopen($file, "w");
            if ($fp) {
                fwrite($fp, $xml);
                fclose($fp);
            }

            return true;
        }

        return false;
    }

    public function callcenter_config_queue_load($esl = null, $company_id = null) {
        $company_id = intval($company_id);
        if ($esl && $company_id > 0) {
            $cmd = 'bgapi callcenter_config queue load ' . $company_id . '@queue';
            $esl->send($cmd);
    
            return true;
        }
    }

    public function callcenter_config_agent_reload($esl = null, $company_id = null, $agents = null) {
        $company_id = intval($company_id);
        if ($esl && $company_id > 0 && $agents != null) {
            foreach ($agents as $agent) {
                $cmd = 'bgapi callcenter_config agent reload ' . $agent['uid'];
                $esl->send($cmd);
            }
            return true;
        }
        return false;
    }

    public function callcenter_config_tier_reload($esl = null, $company_id = null, $agents = null) {
        $company_id = intval($company_id);
    
        if ($esl && $company_id > 0 && $agents != null) {
            foreach ($agents as $agent) {
                $cmd = 'bgapi callcenter_config tier reload ' . $company_id . '@queue ' . $agent['uid'];
                $esl->send($cmd);
            }

            return true;
        }

        return false;
    }

    public function reloadxml($esl = null) {
        if ($esl) {
            $esl->send('bgapi reloadxml');
            return true;
        }

        return false;
    }

    public function sofia_exten_unregister($esl = null, $uid = null) {
        if ($esl && $uid != null) {
            $esl->send('bgapi sofia profile internal flush_inbound_reg ' . $uid);
            return true;
        }
        return false;
    }

    public function sofia_rescan($esl = null) {
        if ($esl) {
            $esl->send('bgapi sofia profile internal rescan all');
            return true;
        }

        return false;
    }

    public function callcenter_agent_logout($esl = null, $uid = null) {
        if ($esl && $uid != null) {
            $esl->send("bgapi callcenter_config agent set status " . $uid . " 'Logged Out'");
            return true;
        }

        return false;
    }

    public function callcenter_agent_delete($esl = null, $uid = null) {
        if ($esl && $uid != null) {
            $esl->send("bgapi callcenter_config agent del " . $uid);
            return true;
        }

        return false;
    }

    public function callcenter_tier_delete($esl = null, $company_id = null, $uid = null) {
        $company_id = intval($company_id);

        if ($esl && $company_id > 0 && $uid != null) {
            $esl->send("bgapi callcenter_config tier del " . $company_id . "@queue " . $uid);
            return true;
        }

        return false;
    }

    public function batchUpdateFilter($data = null) {
        $buff = null;

        if (isset($data['password'])) {
            $password = $this->filter->sanitize($data['password'], 'string');
            $len = mb_strlen($password, 'utf-8');
            if ($len > 7) {
                $buff['password'] = $password;
            } else {
                $buff['password'] = false;
            }
        } else {
            $buff['password'] = false;
        }

        if (isset($data['type'])) {
            $type = $this->filter->sanitize($data['icon'], 'alphanum');
            if ($type === 'on') {
                $buff['type'] = true;
            } else {
                $buff['type'] = false;
            }
        } else {
            $buff['type'] = false;
        }

        if (isset($data['name'])) {
            $name = $this->filter->sanitize($data['name'], 'alphanum');
            if ($name === 'on') {
                $buff['name'] = true;
            } else {
                $buff['name'] = false;
            }
        } else {
            $buff['name'] = false;
        }

        if (isset($data['icon'])) {
            $icon = $this->filter->sanitize($data['icon'], 'alphanum');
            if ($icon === 'on') {
                $buff['icon'] = true;
            } else {
                $buff['icon'] = false;
            }
        } else {
            $buff['icon'] = false;
        }

        if (isset($data['web'])) {
            $web = $this->filter->sanitize($data['web'], 'alphanum');
            if ($web === 'on') {
                $buff['web'] = 1;
            } else {
                $buff['web'] = 0;
            }
        } else {
            $buff['web'] = 0;
        }

        if (isset($data['calls'])) {
            $calls = $this->filter->sanitize($data['calls'], 'alphanum');
            if ($calls === 'on') {
                $buff['calls'] = 1;
            } else {
                $buff['calls'] = 0;
            }
        } else {
            $buff['calls'] = 0;
        }

        return $buff;
    }
}
