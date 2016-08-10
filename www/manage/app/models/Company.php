<?php

class Company {
    private $db = null;
    private $pbx = null;
    private $redis = null;
    private $cookies = null;
    private $filter = null;
    
    public function __construct($app) {
        $this->db = $app->db;
        $this->pbx = $app->pbx;
        $this->redis = $app->redis;
        $this->cookies= $app->cookies;
        $this->filter = $app->filter;
    }
    
    public function isExist($company_id = null) {
        $company_id = intval($company_id);
        if ($company_id > 0) {
            return $this->redis->exists('company.' . $company_id);
        }
        return false;
    }

    public function get($company_id = null) {
        $company_id = intval($company_id);
        if ($company_id > 0 && $this->isExist($company_id)) {
            $sql = "SELECT * FROM company WHERE id = " . $company_id;
            $result = $this->db->fetchOne($sql);
            if ($result) {
                return $result;
            }
        }
        return null;
    }

    public function getAll() {
        $sql = "SELECT * FROM company ORDER BY id";
        $result = $this->db->fetchAll($sql);
        if ($result && count($result) > 0) {
            return $result;
        }
        return null;
    }

    public function getAgent($company_id = null) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $sql = "SELECT * FROM users WHERE type in(2, 3) AND company = " . $company_id . " ORDER BY uid";
            $result = $this->db->fetchAll($sql);
            if ($result) {
                return $result;
            }
        }

        return null;
    }
    
    public function create($data = null) {
        $data = $this->createfilter($data);
        if ($data != null) {
            $success = $this->db->insertAsDict('company', [
                'name' => $data['name'],
                'concurrent' => $data['concurrent'],
                'billing' => $data['billing'],
                'level' => 1,
                'sound_check' => $data['sound_check'],
                'data_filter' => $data['data_filter'],
                'create_time' => date('Y-m-d H:i:s')
                ]);

            if ($success) {
                $sql = "SELECT last_value FROM company_id_seq WHERE sequence_name = 'company_id_seq'";
                $result = $this->db->fetchOne($sql);
                if ($result) {
                    $company_id = intval($result['last_value']);
                    if ($company_id > 0) {
                        $this->redis->hMSet('company.' . $company_id, [
                            'id' => $company_id,
                            'name' => $data['name'],
                            'task' => 0,
                            'concurrent' => $data['concurrent'],
                            'billing' => $data['billing'],
                            'level' => 1,
                            'sound_check' => $data['sound_check'],
                            'data_filter' => $data['data_filter'],
                            'create_time' => date('Y-m-d H:i:s')
                        ]);
                        $success = $this->db->insertAsDict('gateway', [
                                        'username' => $data['username'],
                                        'password' => $data['password'],
                                        'ip_addr' => $data['ipaddr'],
                                        'company' => $company_id,
                                        'registered' => 1
                                    ]);

                        $esl = new ESLconnection(ESL_HOST, ESL_PORT, ESL_PASSWORD);

                        if ($success) {
                            $this->fsAddGateway($esl, $company_id, $data['username'], $data['password'], $data['ipaddr']);
                        }

                        $this->fsQueueCreate($company_id);
                        $this->callcenter_config_queue_load($esl, $company_id);

                        if ($esl) $esl->disconnect();

                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function update($company_id = null, $data = null) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $result = $this->updateFilter($data);
            if ($result != null) {
                $success = $this->db->updateAsDict('company', $result, 'id = ' . $company_id);
                if ($success) {
                    $this->redis->hMSet('company.' . $company_id, $result);
                    return true;
                }
            }
        }

        return false;
    }

    public function delete($esl = null, $company_id = null) {
        $company_id = intval($company_id);

        if ($esl && $company_id > 0 && $this->isExist($company_id)) {
            $sql = "SELECT uid FROM users WHERE type in(2, 3) AND company = " . $company_id;
            $result = $this->db->fetchAll($sql);
            if ($result && count($result) > 0) {
                foreach ($result as $user) {
                    /* clearup user for redis */
                    $this->redis->delete('user.' . $user['uid']);

                    /* clearup user session */
                    $this->redis->delete('session.' . $user['uid']);

                    /* clearup user for freeswitch */
                    unlink('/usr/local/freeswitch/conf/directory/default/' . $user['uid'] . '.xml');

                    /* unregister user exten */
                    $esl->send('bgapi sofia profile internal flush_inbound_reg ' . $user['uid']);
                }
            }

            /* clearup user for postgresql */
            $this->db->delete('users', 'company = ' . $company_id);
            
            /* kill freeswitch gateway */
            $this->db->delete('gateway', 'company = ' . $company_id);
            unlink('/usr/local/freeswitch/conf/sip_profiles/external/trunk.' . $company_id . '.xml');
            $esl->send('bgapi sofia profile external killgw trunk.' . $company_id . '.gw');

            /* clearup callcenter xml file*/
            unlink('/usr/local/freeswitch/conf/queues/queue.' . $company_id . '.xml');
            unlink('/usr/local/freeswitch/conf/agents/agent.' . $company_id . '.xml');
            unlink('/usr/local/freeswitch/conf/tiers/tiers.' . $company_id . '.xml');
            
            /* clearup company product */
            $sql = "SELECT * FROM product WHERE company = " . $company_id;
            $result = $this->db->fetchAll($sql);
            if ($result && count($result) > 0) {
                foreach ($result as $product) {
                    $this->redis->delete('product.' . $product['id']);
                }
            }
            $this->db->delete('product', 'company = ' . $company_id);

            /* clearup company */
            $this->redis->delete('company.' . $company_id);
            $this->db->delete('company', 'id = ' . $company_id);

            /* clearup company task */
            $reply = $this->redis->sMembers('taskpool.' . $company_id);
            if ($reply && count($reply) > 0) {
                foreach ($reply as $task) {
                    $this->redis->delete('task.' . $task);
                    $this->redis->delete('data.' . $task);
                }
            }

            $this->delete('taskpool.' . $company_id);

            $esl->send('bgapi reloadxml');
            return true;
        }

        return false;
    }
    
    public function getUser($company_id = null) {
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

    public function getStatus($company_id = null) {
        $company_id = intval($company_id);

        $status = ['company' => '未知公司', 'task' => '未知任务', 'concurrent' => 0, 'playback' => 0, 'login' => 0, 'talking' => 0];
        if ($company_id > 0 && $this->isExist($company_id)) {
            $company = $this->redis->hMGet('company.' . $company_id, ['name', 'task']);
            $status['company'] = $company['name'];
            if ($company['task'] != '0') {
                $status['task'] = $this->redis->hGet('task.' . $company['task'], 'name');
            } else {
                $status['task'] = '未知任务';
            }
            $status['concurrent'] = $this->getConcurrent($company_id);
            $status['playback'] = $this->getPlayback($company_id);
            $status['login'] = $this->getLoginAgent($company_id);
            $status['talking'] = $this->getTalking($company_id);
        }

        return $status;
    }

    public function updateFilter($data = null) {
        $buff = null;

        if (isset($data['name'])) {
            $name = str_replace(" ", "", $this->filter->sanitize($data['name'], 'string'));
            $len = mb_strlen($name, 'utf-8');
            if ($len > 0) {
                $buff['name'] = htmlspecialchars($name, ENT_QUOTES);
            }
        }

        if (isset($data['level'])) {
            $level = intval($data['level']);
            $allow = [1, 2, 3];
            if (in_array($level, $allow, true)) {
                $buff['level'] = $level;
            }
        }

        if (isset($data['concurrent'])) {
            $concurrent = intval($data['concurrent']);
            if ($concurrent > 0 && $concurrent <= 500) {
                $buff['concurrent'] = $concurrent;
            }
        }

        if (isset($data['billing'])) {
            $billing = $this->filter->sanitize($data['billing'], 'alphanum');
            $len = mb_strlen($billing, 'utf-8');
            if ($len > 0) {
                $buff['billing'] = $billing;
            }
        }

        if (isset($data['sound_check'])) {
            $sound_check = $this->filter->sanitize($data['sound_check'], 'alphanum');
            if ($sound_check === 'on') {
                $buff['sound_check'] = 1;
            }
        } else {
            $buff['sound_check'] = 0;
        }

        if (isset($data['data_filter'])) {
            $data_filter = $this->filter->sanitize($data['data_filter'], 'alphanum');
            if ($data_filter === 'on') {
                $buff['data_filter'] = 1;
            }
        } else {
            $buff['data_filter'] = 0;
        }

        return $buff;

    }

    public function createfilter($data = null) {
        $buff = null;

        if (isset($data['name'])) {
            $name = str_replace(" ", "", $this->filter->sanitize($data['name'], 'string'));
            $len = mb_strlen($name, 'utf-8');
            if ($len > 0) {
                $buff['name'] = $name;
            } else {
                $buff['name'] = '未命名';
            }
        } else {
            $buff['name'] = '未命名';
        }

        if (isset($data['username'])) {
            $username = $this->filter->sanitize($data['username'], 'alphanum');
            $len = mb_strlen($username, 'utf-8');
            if ($len > 0) {
                $buff['username'] = $username;
            } else {
                return null;
            }
        } else {
            return null;
        }

        if (isset($data['password'])) {
            $password = $this->filter->sanitize($data['password'], 'string');
            $len = mb_strlen($password, 'utf-8');
            if ($len > 0) {
                $buff['password'] = $password;
            } else {
                return null;
            }
        } else {
            return null;
        }

        if (isset($data['ipaddr'])) {
            $ipaddr = str_replace(" ", "", $this->filter->sanitize($data['ipaddr'], 'string'));
            $len = mb_strlen($ipaddr, 'utf-8');
            if ($len > 0) {
                $buff['ipaddr'] = $ipaddr;
            } else {
                return null;
            }
        } else {
            return null;
        }

        if (isset($data['concurrent'])) {
            $concurrent = intval($data['concurrent']);
            if ($concurrent > 0 && $concurrent <= 500) {
                $buff['concurrent'] = $concurrent;
            } else {
                $buff['concurrent'] = 120;
            }
        } else {
            $buff['concurrent'] = 120;
        }

        if (isset($data['billing'])) {
            $billing = $this->filter->sanitize($data['billing'], 'alphanum');
            $len = mb_strlen($billing, 'utf-8');
            if ($len > 0) {
                $buff['billing'] = $billing;
            } else {
                $buff['billing'] = 'unknown';
            }
        } else {
            $buff['billing'] = 'unknown';
        }

        if (isset($data['sound_check'])) {
            $buff['sound_check'] = 1;
        } else {
            $buff['sound_check'] = 0;
        }

        if (isset($data['data_filter'])) {
            $buff['data_filter'] = 1;
        } else {
            $buff['data_filter'] = 0;
        }
        
        return $buff;
    }

    public function fsAddGateway($esl = null, $company_id = null, $username = null, $password = null, $ipaddr = null) {
        $company_id = intval($company_id);

        if ($esl && $company_id > 0) {
            $this->fsWriteXmlGateway($company_id, $username, $password, $ipaddr);
            sleep(1);
            $esl->send('bgapi sofia profile external rescan');
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

    public function fsQueueCreate($company_id = null) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $xml = "<include>\n";
            $xml .= '  <queue name="' . $company_id . '@queue">'."\n";
            $xml .= '    <param name="strategy" value="longest-idle-agent"/>'."\n";
            $xml .= '    <param name="moh-sound" value="$${hold_music}"/>'."\n";
            $xml .= '    <param name="record-template" value="$${recordings_dir}/${strftime(%Y/%m/%d}/${uuid}.wav"/>'."\n";
            $xml .= '    <param name="time-base-score" value="system"/>'."\n";
            $xml .= '    <param name="max-wait-time" value="45"/>'."\n";
            $xml .= '    <param name="max-wait-time-with-no-agent" value="30"/>'."\n";
            $xml .= '    <param name="max-wait-time-with-no-agent-time-reached" value="5"/>'."\n";
            $xml .= '    <param name="tier-rules-apply" value="false"/>'."\n";
            $xml .= '    <param name="tier-rule-wait-second" value="300"/>'."\n";
            $xml .= '    <param name="tier-rule-wait-multiply-level" value="true"/>'."\n";
            $xml .= '    <param name="tier-rule-no-agent-no-wait" value="false"/>'."\n";
            $xml .= '    <param name="discard-abandoned-after" value="60"/>'."\n";
            $xml .= '    <param name="abandoned-resume-allowed" value="false"/>'."\n";
            $xml .= '  </queue>'."\n";
            $xml .= "</include>\n";
    
            $file = '/usr/local/freeswitch/conf/queues/queue.' . $company_id . '.xml';
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

        return false;
    }

    public function getConcurrent($company_id = null) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $sql = "SELECT count(uuid) FROM channels WHERE initial_cid_num = '" . $company_id . "'";
            $result = $this->pbx->fetchOne($sql);
            if ($result) {
                return intval($result['count']);    
            }
        }

        return 0;
    }

    public function getLoginAgent($company_id = null) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $queue = $company_id . '@queue';
            $sql = "SELECT count(name) FROM agents WHERE name in (SELECT agent FROM tiers WHERE queue = '" . $queue . "') AND status in ('Available', 'Available (On Demand)', 'On Break')";
            $result = $this->pbx->fetchOne($sql);
            if ($result) {
                return intval($result['count']);
            }
        }

        return 0;
    }

    public function getPlayback($company_id = null) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $sql = "SELECT count(uuid) FROM channels WHERE application = 'playback' AND initial_cid_num = '" . $company_id . "'";
            $result = $this->pbx->fetchOne($sql);
            if ($result) {
                return intval($result['count']);
            }
        }

        return 0;
    }

    public function getTalking($company_id = null) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $queue = $company_id . '@queue';
            $sql = "SELECT count(name) FROM agents WHERE name in (SELECT agent FROM tiers WHERE queue = '" . $queue . "') AND state = 'In a queue call' AND status in ('Available', 'Available (On Demand)', 'On Break')";
            $result = $this->pbx->fetchOne($sql);
            if ($result) {
                return intval($result['count']);
            }
        }

        return 0;
    }

    public function doppelganger($company_id = null) {
        $company_id = intval($company_id);

        if ($company_id > 0 && $this->isExist($company_id)) {
             /* 生成随机token字符串 */
            $random = new \Phalcon\Security\Random();

            /* generate temporary uid */
            $uid = $random->hex(4);
            while ($this->redis->exists('user.' . $uid)) {
                $uid = $random->hex(4);
            }

            $password = sha1(md5($random->hex(16)));

            /* write temp user */
            $attr = ['uid' => $uid, 'name' => 'admin', 'type' => 1, 'company' => $company_id, 'password' => $password,
                     'icon' => '007', 'status' => 1, 'callerid' => 'null', 'phone' => 'null', 'web' => 1, 'calls' => 1,
                     'last_login' => '1970-01-01 08:00:00', 'last_ipaddr' => '0.0.0.0', 'create_time' => '1970-01-01 08:00:00'];
            $this->redis->hMSet('user.' . $uid, $attr);
            $this->redis->setTimeout('user.' . $uid, 7200);

            /* 将uuid与token写入redis */
            $token = $random->hex(16);
            $this->redis->hMSet('session.'.$uid, ['token' => $token, 'ipaddr' => $_SERVER["REMOTE_ADDR"]]);
            $this->redis->setTimeout('session.'.$uid, 3600);

            /* 写入用户端cookie */
            $this->cookies->set('uuid', $uid, time() + 3600);
            $this->cookies->set('token', $token, time() + 3600);

            return true;
        }

        return false;
    }
}