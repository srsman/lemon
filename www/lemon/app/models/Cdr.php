<?php

class Cdr {
    private $db = null;
    private $cdr = null;
    private $redis = null;
    private $filter = null;
    
    public function __construct ($app) {
        $this->db = $app->db;
        $this->cdr = $app->cdr;
        $this->redis = $app->redis;
        $this->filter = $app->filter;
    }

    public function query($company_id, $where) {
        $company_id = intval($company_id);
        if ($company_id > 0) {
            $where = $this->whereFilter($where);
            if ($where != null) {
                $table = 'cdr';
                if ($this->isTableExist($table)) {
                    $sql = "SELECT * FROM " . $table . " WHERE";
                    if (isset($where['id'])) {
                        $sql .= " id < " . $where['id'] . " AND company = " . $company_id;
                    } else {
                        $sql .= " company = " . $company_id;
                    }

                    if (isset($where['caller'])) {
                        $sql  .= " AND caller = '" . $where['caller'] . "'";
                    }

                    if (isset($where['callee'])) {
                        $sql .= " AND callee = '" . $where['callee'] . "'";
                    }

                    if (isset($where['duration'])) {
                        $sql .= " AND duration > " . $where['duration'];
                    }

                    $sql .= " AND create_time BETWEEN '" . $where['start'] . "' AND '" . $where['end'] . "' ORDER BY create_time DESC LIMIT 45";
                    $result = $this->cdr->fetchAll($sql);
                    return $result ? $result : null;
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

        if (isset($where['caller'])) {
            $caller = $this->filter->sanitize($where['caller'], 'int');
            $len = mb_strlen($caller, 'utf-8');
            if ($len > 0) {
                $buff['caller'] = $caller;
            }
        }

        if (isset($where['called'])) {
            $called = $this->filter->sanitize($where['called'], 'int');
            $len = mb_strlen($called, 'utf-8');
            if ($len > 0) {
                $buff['callee'] = $called;
            }
        }
        
        if (isset($where['billsec'])) {
            $billsec = intval($where['billsec']);
            if ($billsec >= 0) {
                $buff['duration'] = $billsec;
            } else {
                $buff['duration'] = 0;
            }
        } else {
            $buff['duration'] = 0;
        }

        if (isset($where['start'])) {
            $start = $this->filter->sanitize($where['start'], 'string');
            if ($this->is_date($start)) {
                $buff['start'] = $start;
            } else {
                $buff['start'] = date('Y-m-d 08:00:00');
            }
        } else {
            $buff['start'] = date('Y-m-d 08:00:00');
        }

        if (isset($where['end'])) {
            $end = $this->filter->sanitize($where['end'], 'string');
            if ($this->is_date($end)) {
                $buff['end'] = $end;
            } else {
                $buff['end'] = date('Y-m-d 20:00:00');
            }
        } else {
            $buff['end'] = date('Y-m-d 20:00:00');
        }

        return $buff;
    }

    public function report($company_id = null, $where) {
        $company_id = intval($company_id);
        if ($company_id > 0) {
            $where = $this->forReportFilter($where);
            if ($where != null) {
                $table = 'cdr';
                if ($this->isTableExist($table)) {
                    $sql = "SELECT caller, callee, duration FROM " . $table . " WHERE company = " . $company_id;
                    $sql .= " AND duration > 0";
                    $sql .= " AND create_time BETWEEN '" . $where['start'] . "' AND '" . $where['end'] . "'";
                    $result = $this->cdr->fetchAll($sql);
                    return $result;
                }
            }
        }

        return null;
    }

    public function forReportFilter($where) {
        $buff = null;

        if (isset($where['start'])) {
            $start = $this->filter->sanitize($where['start'], 'string');
            if ($this->is_date($start)) {
                $buff['start'] = $start;
            } else {
                $buff['start'] = date('Y-m-d 08:00:00');
            }
        } else {
            $buff['start'] = date('Y-m-d 08:00:00');
        }

        if (isset($where['end'])) {
            $end = $this->filter->sanitize($where['end'], 'string');
            if ($this->is_date($end)) {
                $buff['end'] = $end;
            } else {
                $buff['end'] = date('Y-m-d 20:00:00');
            }
        } else {
            $buff['end'] = date('Y-m-d 20:00:00');
        }

        if ((strtotime($buff['end']) - strtotime($buff['start'])) > 86400) {
            return null;
        }

        return $buff;
    }

    private function is_date($date = null, $format = 'Y-m-d H:i:s') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }
    
    private function tablePars($date = null) {
        if ($date != null && $this->is_date($date)) {
            $time = strtotime($date);
            if ($time) {
                return 'cdr_' . date('Ym', $time);
            }
        }

        return null;
    }

    private function isTableExist($table = null) {
        if ($table != null) {
            $sql = "SELECT count(*) FROM pg_class WHERE relname = '" . $table . "'";
            $result = $this->cdr->fetchOne($sql);
            if ($result && $result['count'] > 0) {
                return true;
            }
        }

        return false;
    }
}
