<?php

class Order {
    private $db = null;
    private $cdr = null;
    private $order = null;
    private $redis = null;
    private $filter = null;
    
    public function __construct ($app) {
        $this->db = $app->db;
        $this->cdr = $app->cdr;
        $this->order = $app->order;
        $this->redis = $app->redis;
        $this->filter = $app->filter;
    }

    public function isExist($order_id) {
        $order_id = intval($order_id);

        if ($order_id > 0) {
            $sql = "SELECT id FROM orders WHERE id = " . $order_id;
            $result = $this->order->query($sql);
            if ($result->numRows() > 0) {
                return true;
            }
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

    public function get($order_id) {
        $order_id = intval($order_id);
        if ($order_id > 0) {
            $sql = "SELECT * FROM orders WHERE id = " . $order_id;
            return $this->order->fetchOne($sql);
        }
        return null;
    }

    public function update($order_id, $quality = 'unknown', $data = null) {
        $order_id = intval($order_id);
        if ($order_id > 0) {
            if ($this->isExist($order_id)) {
                $data = $this->filterUpdate($data);
                if ($data != null) {
                    $data['quality'] = $quality;
                    $data['quality_time'] = date('Y-m-d H:i:s', time());
                    $success = $this->order->updateAsDict('orders', $data, 'id = ' . $order_id);
                    if ($success) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function get_orderRecord($company_id = null, $uid = null, $phone = null) {
        $company_id = intval($company_id);

        if ($company_id > 0) {
            $uid = $this->filter->sanitize($uid, 'alphanum');
            $phone = $this->filter->sanitize($phone, 'alphanum');

            $table = 'cdr';
            $sql = "SELECT record FROM " . $table . " WHERE company = " . $company_id . " AND caller in('" . $uid . "', '" . ltrim($phone, '0') . "') AND callee in('" . $uid . "', '" . ltrim($phone, '0') . "') ORDER BY create_time DESC"; 

            $result = $this->cdr->fetchOne($sql);
            if ($result && count($result) > 0) {
                return $result['record'];
            }
        }

        return null;
    }

    public function create($uid = null, $company_id = null, $data = null) {
        $company_id = intval($company_id);

        if ($uid != null && $company_id > 0 && $this->isCompanyExist($company_id)) {
            $data = $this->createFilter($data);
            if ($data != null) {
                $data['company'] = $company_id;
                $data['creator'] = strval($uid);
                $data['quality'] = 'null';
                $data['reason'] = 'null';
                $data['status'] = 1;
                $data['express_id'] = 'null';
                $data['logistics_status'] = 'null';
                $data['create_time'] = date('Y-m-d H:i:s');
                $data['quality_time'] = date('Y-m-d H:i:s');
                $data['delivery_time'] = date('Y-m-d H:i:s');
                $success = $this->order->insertAsDict('orders', $data);
                if ($success) {
                    return true;
                }
            }
        }

        return false;
    }

    public function filterUpdate($data = null) {
        $buff = null;

        if (isset($data['name'])) {
            $name = str_replace(" ", "", $this->filter->sanitize($data['name'], 'string'));
            $len = mb_strlen($name, 'utf-8');
            if ($len > 0) {
                $buff['name'] = htmlspecialchars($name, ENT_QUOTES);
            }
        }

        if (isset($data['phone'])) {
            $phone = $this->filter->sanitize($data['phone'], 'alphanum');
            $len = mb_strlen($phone, 'utf-8');
            if ($len > 10) {
                $buff['phone'] = $phone;
            }
        }

        if (isset($data['product'])) {
            $product = intval($data['product']);
            if ($product > 0) {
                if ($this->redis->exists('product.' . $product)) {
                    $buff['product'] = $product;
                }
            }
        }

        if (isset($data['telephone'])) {
            $telephone = $this->filter->sanitize($data['telephone'], 'alphanum');
            $len = mb_strlen($telephone, 'utf-8');
            if ($len > 10) {
                $buff['telephone'] = $telephone;
            }
        }

        if (isset($data['number'])) {
            $number = intval($data['number']);
            if ($number > 0) {
                $buff['number'] = $number;
            }
        }

        if (isset($data['address'])) {
            $address = str_replace(" ", "", $this->filter->sanitize($data['address'], 'string'));
            $len = mb_strlen($address, 'utf-8');
            if ($len > 0) {
                $buff['address'] = htmlspecialchars($address, ENT_QUOTES);
            }
        }

        if (isset($data['comment'])) {
            $comment = str_replace(" ", "", $this->filter->sanitize($data['comment'], 'string'));
            $len = mb_strlen($comment, 'utf-8');
            if ($len > 0) {
                $buff['comment'] = htmlspecialchars($comment, ENT_QUOTES);
            }
        }

        if (isset($data['status'])) {
            $status = intval($data['status']);
            $allow = [1, 2, 3, 4, 5];
            if (in_array($status, $allow, true)) {
                $buff['status'] = $status;
            }
        }

        if (isset($data['reason'])) {
            $reason = str_replace(" ", "", $this->filter->sanitize($data['reason'], 'string'));
            $len = mb_strlen($reason, 'utf-8');
            if ($len > 0) {
                $buff['reason'] = htmlspecialchars($reason, ENT_QUOTES);
            }
        }

        return $buff;
    }

    public function createFilter($data = null) {
        $buff = null;

        if (isset($data['name'])) {
            $name = str_replace(" ", "", $this->filter->sanitize($data['name'], 'string'));
            $len = mb_strlen($name, 'utf-8');
            if ($len > 0) {
                $buff['name'] = htmlspecialchars($name, ENT_QUOTES);
            } else {
                return null;
            }
        } else {
            return null;
        }

        if (isset($data['phone'])) {
            $phone = $this->filter->sanitize($data['phone'], 'alphanum');
            $len = mb_strlen($phone, 'utf-8');
            if ($len > 10) {
                $buff['phone'] = $phone;
            } else {
                return null;
            }
        } else {
            return null;
        }

        if (isset($data['telephone'])) {
            $telephone = $this->filter->sanitize($data['telephone'], 'alphanum');
            $len = mb_strlen($telephone, 'utf-8');
            if ($len > 0) {
                $buff['telephone'] = $telephone;
            } else {
                $buff['telephone'] = $buff['phone'];
            }
        } else {
            $buff['telephone'] = $buff['phone'];
        }

        if (isset($data['address'])) {
            $address = str_replace(" ", "", $this->filter->sanitize($data['address'], 'string'));
            $len = mb_strlen($address);
            if ($len > 0) {
                $buff['address'] = htmlspecialchars($address, ENT_QUOTES);
            } else {
                return null;
            }
        } else {
            return null;
        }

        if (isset($data['product'])) {
            $product = intval($data['product']);
            if ($product > 0) {
                $buff['product'] = $product;
            } else {
                $buff['product'] = 0;
            }
        } else {
            $buff['product'] = 0;
        }

        if (isset($data['number'])) {
            $number = intval($data['number']);
            if ($number > 0) {
                $buff['number'] = $number;
            } else {
                $buff['number'] = 1;
            }
        } else {
            $buff['number'] = 1;
        }

        if (isset($data['comment'])) {
            $comment = str_replace(" ", "", $this->filter->sanitize($data['comment'], 'string'));
            $len = mb_strlen($comment, 'utf-8');
            if ($len > 0) {
                $buff['comment'] = htmlspecialchars($comment, ENT_QUOTES);
            } else {
                $buff['comment'] = 'null';
            }
        } else {
            $buff['comment'] = 'null';
        }

        return $buff;
    }
}
