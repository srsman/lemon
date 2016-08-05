<?php

class Product {
    private $db = null;
    private $redis = null;
    private $filter = null;
    
    public function __construct ($app) {
        $this->db = $app->db;
        $this->redis = $app->redis;
        $this->filter = $app->filter;
    }

    public function isExist($product_id) {
        $product_id = intval($product_id);
        return $this->redis->exists('product.' . $product_id);
    }

    public function get($product_id) {
        $product_id = intval($product_id);

        if ($this->isExist($product_id)) {
            $column = ['id', 'name', 'price', 'inventory', 'company', 'remark', 'create_time'];
            $reply = $this->redis->hMGet('product.' . $product_id, $column);
            return $reply;
        }

        return null;
    }

    public function create($company_id, $data) {
        if (!is_array($data)) {
            return false;
        }

        $company_id = intval($company_id);
        $data = $this->filter($data, true);
        $data['company'] = $company_id;

        $success = $this->db->insertAsDict('product', $data);
        if ($success) {
            $sql = "SELECT last_value FROM product_id_seq WHERE sequence_name = 'product_id_seq'";
            $result = $this->db->fetchOne($sql);
            if ($result) {
                $product_id = $result['last_value'];
                $data['id'] = $product_id;
                $this->redis->hMSet('product.' . $product_id, $data);
            }
            return true;
        }

        return false;
    }

    public function update($product_id, $data) {
        if (!is_array($data)) {
            return false;
        }

        $product_id = intval($product_id);
        if ($this->isExist($product_id)) {
            $data = $this->filter($data);

            $success = $this->db->updateAsDict('product', $data, 'id = ' . $product_id);
            if ($success) {
                $this->redis->hMSet('product.' . $product_id, $data);
                return true;
            }
        }

        return false;
    }

    public function delete($product_id) {
        $product_id = intval($product_id);
        if ($this->isExist($product_id)) {
            $success = $this->db->delete("product", "id = ?", [$product_id]);
            if ($success) {
                $this->redis->delete('product.' . $product_id);
                return true;
            }
        }

        return false;
    }

    public function filter($data, $defValue = false) {
        $buff = null;
        
        if (isset($data['name'])) {
            $name = $this->filter->sanitize($data['name'], 'striptags');
            $len = mb_strlen($name, 'utf-8');
            if ($len > 0) {
                $buff['name'] = $name;
            } else if ($defValue) {
                $buff['name'] = 'unknown';
            }
        }

        if (isset($data['price'])) {
            $price = intval($data['price']);
            if ($price > 0) {
                $buff['price'] = $price;
            } else if ($defValue) {
                $buff['price'] = 999;
            }
        }
        
        if (isset($data['inventory'])) {
            $inventory = intval($data['inventory']);
            if ($inventory > 0) {
                $buff['inventory'] = $inventory;
            } else if ($defValue) {
                $buff['inventory'] = 999;
            }
        }

        if (isset($data['remark'])) {
            $remark = $this->filter->sanitize($data['remark'], 'string');
            $len = mb_strlen($remark, 'utf-8');
            if ($len > 0 && $len < 18) {
                $buff['remark'] = $remark;
            } else if ($defValue) {
                $buff['remark'] = 'no description information';
            }
        }


        if ($defValue) {
            $buff['create_time'] = date('Y-m-d H:i:s');
        }


        return $buff;
    }
}
