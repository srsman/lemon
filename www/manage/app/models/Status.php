<?php

class Status {
    private $redis = null;
    
    public function __construct ($app) {
        $this->redis = $app->redis;
    }

    public function getAll($esl = null) {
        $status['uptime'] = $this->get_uptime();
        $status['cpuinfo'] = $this->get_cpuinfo();
        $status['loadavg'] = $this->get_loadavg();
        $status['memory'] = $this->get_memory();
        $status['hard'] = $this->get_hard_disk();
        $status['uname'] = $this->get_uname();
        if ($esl) {
            $status['pbx'] = $this->get_pbx($esl);
        } else {
            $status['pbx'] = '没有pbx相关信息';
        }

        return $status;
    }

    public function get_uptime() {
        $str = "";
        $uptime = "";
    
        if (($str = @file("/proc/uptime")) === false) {
            return "";
        }
    
        $str = explode(" ", implode("", $str));
        $str = trim($str[0]);
        $min = $str / 60;
        $hours = $min / 60;
        $days = floor($hours / 24);
        $hours = floor($hours - ($days * 24));
        $min = floor($min - ($days * 60 * 24) - ($hours * 60));

        if ($days !== 0) {
            $uptime = $days."天";
        }
        if ($hours !== 0) {
            $uptime .= $hours."小时";
        }

        $uptime .= $min."分钟";

        return $uptime;
    }
    
    public function get_cpuinfo() {
        if (($str = @file("/proc/cpuinfo")) === false) {
            return false;
        }
    
        $str = implode("", $str);
        @preg_match_all("/model\s+name\s{0,}\:+\s{0,}([\w\s\)\(\@.-]+)([\r\n]+)/s", $str, $model);

        if (false !== is_array($model[1])) {
            $core = sizeof($model[1]);
            $cpu = $model[1][0].' x '.$core.'核';
            return $cpu;
        }

        return "Unknown";
    }
    
    public function get_hard_disk() {
        $total = round(@disk_total_space(".")/(1024*1024*1024),3); //总
        $avail = round(@disk_free_space(".")/(1024*1024*1024),3); //可用
        $use = $total - $avail; //已用
        $percentage = (floatval($total) != 0) ? round($avail / $total * 100, 0) : 0;

        return ['total' => $total, 'avail' => $avail, 'use' => $use, 'percentage' => $percentage];
    }
    
    public function get_loadavg() {
        if (($str = @file("/proc/loadavg")) === false) {
            return 'Unknown';
        }

        $str = explode(" ", implode("", $str));
        $str = array_chunk($str, 4);
        $loadavg = implode(" ", $str[0]);

        return $loadavg;
    }
    
    public function get_memory() {
        if (false === ($str = @file("/proc/meminfo"))) {
            return ['total' => 0, 'free' => 0, 'use' => 0, 'percentage' => 0];
        }
    
        $str = implode("", $str);
        preg_match_all("/MemTotal\s{0,}\:+\s{0,}([\d\.]+).+?MemFree\s{0,}\:+\s{0,}([\d\.]+).+?Cached\s{0,}\:+\s{0,}([\d\.]+).+?SwapTotal\s{0,}\:+\s{0,}([\d\.]+).+?SwapFree\s{0,}\:+\s{0,}([\d\.]+)/s", $str, $buf);
        preg_match_all("/Buffers\s{0,}\:+\s{0,}([\d\.]+)/s", $str, $buffers);
    
        $total = round($buf[1][0] / 1024, 2);
        $free = round($buf[2][0] / 1024, 2);
        $buffers = round($buffers[1][0] / 1024, 2);
        $cached = round($buf[3][0] / 1024, 2);
        $use = $total - $free - $cached - $buffers; //真实内存使用
        $percentage = (floatval($total) != 0) ? round($use / $total * 100, 0) : 0; //真实内存使用率

        return ['total' => $total, 'free' => $free, 'use' => $use, 'percentage' => $percentage];
    }
    
    public function get_uname() {
        return php_uname();
    }

    public function get_pbx($esl = null) {
        if ($esl) {
            $e = $esl->sendRecv('api status');
            return $e->getBody();
        }

        return 'no pbx information';
    }
}
