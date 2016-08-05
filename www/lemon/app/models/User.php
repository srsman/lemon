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

    public function get($uid = null, $data = null) {
        $uid = $this->filter->sanitize($uid, 'alphanum');
        if ($this->isExist($uid)) {
            $column = ['uid', 'name', 'password', 'type', 'phone', 'callerid', 'company', 'icon', 'status', 'calls', 'web', 'create_time', 'last_login', 'last_ipaddr'];
            if (is_array($data)) {
                $fields = null;
                foreach ($data as $value) {
                    $value = $this->filter->sanitize($value, 'string');
                    if (in_array($value, $column, true)) {
                        $fields[] = $value;
                    }
                }

                if ($fields != null) {
                    $reply = $this->redis->hMGet('user.'.$uid, $fields);
                    return $reply;
                }
            }
            
            $reply = $this->redis->hMGet('user.'.$uid, $column);
            return $reply;
        }
        
        return null;
    }
    
    public function update($esl = null, $uid = null, $data = null) {
        $uid = $this->filter->sanitize($uid, 'alphanum');
        if ($this->isExist($uid)) {
            $data = $this->filter($data);
            if ($data != null && count($data) > 0) {
                $user = $this->get($uid);
                $success = $this->db->updateAsDict('users', $data, 'uid = ' . $this->db->escapeString($uid));
                if ($success) {
                    $this->redis->hMSet('user.' . $uid, $data);

                    /* check update password */
                    if (isset($data['password'])) {
                        if ($user['password'] != $data['password']) {
                            $user = $this->get($uid);
                            $this->fs_write_directory($esl, $user);
                            $this->fs_sofia_rescan($esl);
                        }
                    }

                    return true;
                }
            }
        }

        return false;
    }
    
    public function filter($data = null) {
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
                $buff['password'] = $password;
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

    public function fs_write_directory($esl = null, $user = null) {
        $uid = isset($user['uid']) ? $this->filter->sanitize($user['uid'], 'alphanum') : null;

        if ($esl && $this->isExist($uid)) {
            if ($user != null) {
                $xml = "<include>\n";
                $xml .= "  <user id=\"" . $user['uid'] . "\">\n";
                $xml .= "    <params>\n";
                $xml .= "      <param name=\"password\" value=\"" . $user['password'] . "\"/>\n";
                $xml .= "    </params>\n";
                $xml .= "    <variables>\n";
                $xml .= "      <variable name=\"toll_allow\" value=\"domestic,international,local\"/>\n";
                $xml .= "      <variable name=\"accountcode\" value=\"" . $user['company'] . "\"/>\n";
                $xml .= "      <variable name=\"user_context\" value=\"default\"/>\n";
                $xml .= "      <variable name=\"effective_caller_id_name\" value=\"Extension " . $user['uid'] . "\"/>\n";
                $xml .= "      <variable name=\"effective_caller_id_number\" value=\"" . $user['uid'] . "\"/>\n";
                $xml .= "      <variable name=\"outbound_caller_id_name\" value=\"" . $user['callerid'] . "\"/>\n";
                $xml .= "      <variable name=\"outbound_caller_id_number\" value=\"" . $user['callerid'] . "\"/>\n";
                $xml .= "      <variable name=\"callgroup\" value=\"techsupport\"/>\n";
                $xml .= "    </variables>\n";
                $xml .= "  </user>\n";
                $xml .= "</include>\n";

                $file = '/usr/local/freeswitch/conf/directory/default/' . $user['uid'] . '.xml';
                $fp = fopen($file, "w");
                if ($fp) {
                    fwrite($fp, $xml);
                    fclose($fp);
                }
                return true;
            }
        }

        return false;
    }

    public function fs_sofia_rescan($esl = null) {
        if ($esl) {
            $cmd = 'bgapi sofia profile internal rescan';
            $esl->send($cmd);
            return true;
        }

        return false;
    }
}
