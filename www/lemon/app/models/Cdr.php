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
                $sql = "SELECT id, caller_id_number, destination_number, start_stamp, billsec, bleg_uuid FROM cdr WHERE accountcode = '{$company_id}'";
                if (isset($where['id'])) {
                    $sql .= " AND id < " . $where['id'];
                }

                if (isset($where['caller_id_number'])) {
                    $sql  .= " AND caller_id_number = '" . $where['caller_id_number'] . "'";
                }

                if (isset($where['destination_number'])) {
                    $sql .= " AND destination_number = '" . $where['destination_number'] . "'";
                } else {
                    $sql .= " AND destination_number not in('7', '9', '001', '002', '003', '004', 'service')";
                }

                if (isset($where['billsec'])) {
                    $sql .= " AND billsec > " . $where['billsec'];
                }

                $sql .= " AND start_stamp BETWEEN '" . $where['start'] . "' AND '" . $where['end'] . "' ORDER BY start_stamp DESC LIMIT 45";
                $result = $this->cdr->fetchAll($sql);
                return $result;
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
                $buff['caller_id_number'] = $caller;
            }
        }

        if (isset($where['called'])) {
            $called = $this->filter->sanitize($where['called'], 'int');
            $len = mb_strlen($called, 'utf-8');
            if ($len > 0) {
                $buff['destination_number'] = $called;
            }
        }
        
        if (isset($where['billsec'])) {
            $billsec = intval($where['billsec']);
            if ($billsec >= 0) {
                $buff['billsec'] = $billsec;
            } else {
                $buff['billsec'] = 0;
            }
        } else {
            $buff['billsec'] = 0;
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
                $sql = "SELECT caller_id_number, destination_number, billsec FROM cdr WHERE accountcode = '{$company_id}'";
                $sql .= " AND destination_number not in ('7', '9', '001', '002', '003', '004', 'service')";
                $sql .= " AND billsec > 0";
                $sql .= " AND start_stamp BETWEEN '{$where['start']}' AND '{$where['end']}'";
                $result = $this->cdr->fetchAll($sql);
                return $result;
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

    private function is_date($date, $format = 'Y-m-d H:i:s') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }
    
}
