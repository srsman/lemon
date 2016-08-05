### 相关依赖包
```
mod_bcg729.tar.gz        freeswitch的G729编码模块
pgbouncer-1.7.2.tar.gz   postgresql数据库连接池
phalcon-2.0.12.tar.gz    phalcon Web 框架
phpredis-2.2.7.tar.gz    php的redis数据库扩展
```
#### mod_bcg729
```
$ tar -zxvf mod_bcg729.tar.gz
$ cd mod_bcg729
$ make
$ make install
```

#### pgbouncer
```
$ tar -zxvf pgbouncer-1.7.2.tar.gz
$ cd pgbouncer-1.7.2
$ ./configure
$ make
$ make install
```

#### phalcon
```
$ tar -zxvf phalcon-2.0.12.tar.gz
$ cd phalcon-2.0.12/build
$ ./install
```

#### phpredis
```
$ tar -zxvf phpredis-2.2.7.tar.gz
$ cd phpredis-2.2.7
$ phpize
$ ./configure
$ make
$ make install
```