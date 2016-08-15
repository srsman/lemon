lemon 是基于 FreeSWITCH 的开源呼叫中心系统，是 tenjin 3.0 的升级版， 并且正式更名为 lemon，Web系统主要使用PHP开发，核心控制模块使用C语言编写，单台服务器可多租户使用。

### 主要功能和特性
* 座席队列监控
* 3种外呼模式
* 简单的订单系统
* 分机注册及状态监控
* 商品管理和语音管理
* 通话录音查询
* 通话记录和通话数据报表
* 集成VOS账户余额查询
* 可定制简单的呼入队列

### 3种外呼模式
1. 群呼转座席自动模式
2. 群呼转座席固定模式
3. 半自动一对一外呼

### 安装教程
* 服务优化
```shell
$ systemctl disable auditd.service
$ systemctl disable firewalld.service
$ systemctl disable microcode.service
$ systemctl disable NetworkManager.service
$ systemctl disable postfix.service
$ systemctl disable tuned.service
```
* 内核参数优化 /etc/sysctl.conf
```shell
net.ipv6.conf.all.disable_ipv6 = 1
net.ipv6.conf.default.disable_ipv6 = 1
net.ipv4.ip_forward = 1
net.ipv4.tcp_syncookies = 1
net.ipv4.tcp_tw_reuse = 1
net.ipv4.tcp_tw_recycle = 1
net.ipv4.tcp_fin_timeout = 30
fs.file-max = 2097152
fs.mqueue.msg_default = 10240
fs.mqueue.msg_max = 10240
fs.mqueue.msgsize_default = 8192
fs.mqueue.msgsize_max = 8192
fs.mqueue.queues_max = 256
```
* 内核参数优化 /etc/security/limits.conf
```shell
* soft nofile 102400
* hard nofile 102400
* soft nproc unlimited
* hard nproc unlimited
```

* 安装 yum 源 epel-release
```shell
$ yum -y install epel-release
$ yum makecache fast
```
* 安装相关依赖软件包和开发库
```shell
$ yum install -y gcc gcc-c++ autoconf automake libtool wget python ncurses-devel zlib-devel openssl-devel re2c
$ yum install -y libcurl-devel pcre-devel speex-devel ldns-devel libedit-devel libxml2-devel e2fsprogs-devel
$ yum install -y libdb4* libidn-devel unbound-devel libuuid-devel lua-devel libsndfile-devel gsm gsm-devel
$ yum install -y libevent libevent-devel hiredis hiredis-devel libconfig libconfig-devel libjpeg-devel
$ yum install -y nginx php php-fpm php-devel php-pgsql php-mcrypt php-mbstring php-pdo php-pgsql redis sqlite-devel
```
* 安装PHP的redis数据库扩展
```shell
$ tar -zxvf phpredis-2.2.7.tar.gz
$ cd phpredis-2.2.7
$ phpize
$ ./configure
$ make
$ make install
```
* 安装 PostgreSQL 数据库
```shell
$ yum install -y postgresql postgresql-server postgresql-devel
$ postgresql-setup initdb
$ systemctl enable postgresql.service
$ systemctl start postgresql.service
```
* 安装 pgbouncer 数据库连接池

```shell
$ tar -zxvf pgbouncer-1.7.2.tar.gz
$ cd pgbouncer-1.7.2
$ ./configure
$ make
$ make install
$ mkdir -p /etc/pgbouncer
$ mkdir -p /var/log/pgbouncer
$ mkdir -p /var/run/pgbouncer
$ chown -R postgres:postgres /var/log/pgbouncer
$ chown -R postgres:postgres /var/run/pgbouncer
$ cp ../config/pgbouncer.ini /etc
$ cp ../config/userlist.txt /etc/pgbouncer
$ cp ../config/pgbouncer.conf /etc/tmpfiles.d
$ cp ../config/pgbouncer.service /etc/systemd/system
$ systemctl enable pgbouncer.service
$ systemctl start pgbouncer.service
```
* 编译安装 FreeSWITCH
```shell
$ wget http://files.freeswitch.org/freeswitch-releases/freeswitch-1.6.9.tar.gz
$ cd freeswitch-1.6.8
$ emacs modules.conf
$ ./configure --disable-debug --disable-libyuv --disable-libvpx --enable-core-pgsql-support
$ make
$ make install
$ ln -s /usr/local/freeswitch/bin/fs_cli /usr/bin/fs_cli
$ ln -s /usr/local/freeswitch/bin/freeswitch /usr/bin/freeswitch
$ mkdir -p /var/service
$ mkdir -p /var/freeswitch
$ chown -R apache:apache /var/service
$ chown -R apache:apache /var/freeswitch
$ chown -R apache:apache /usr/local/freeswitch
```
* 安装 ESL PHP模块
```sehll
$ cd libs/esl
$ make phpmod
$ cp php/ESL.so /usr/lib64/php/modules
```

* 安装 mod_bcg729 语音编码
```shell
$ tar -zxvf mod_bcg729.tar.gz
$ cd mod_bcg729
$ make
$ make install
```
### FreeSWITCH 中文语音包 (只包含部分中文语音)
github 下载地址: [freeswitch-sound-cn](https://github.com/log2k/freeswitch-sound-cn/archive/master.zip) 或者 git clone
```
git clone https://github.com/log2k/freeswitch-sound-cn.git
cp -R freeswitch-sound-cn /usr/local/freeswitch/sounds
```
