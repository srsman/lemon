/*
  fastcgi restfull api server 2015/11/03 by ilog2k@gmail.com
*/

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <libconfig.h>
#include "config.h"

bool load_conf_init(const char *file, conf_t *conf) {
    config_t cfg;
    config_init(&cfg);
    
    if (!config_read_file(&cfg, file)) {
        conf->err = "error: config.c load_conf_init() unable to open configuration file or file format error\n";
        config_destroy(&cfg);
        return false;
    }

    /* read log file configure */
    const char *log_file;
    if (!config_lookup_string(&cfg, "log_file", &log_file)) {
        strcpy(conf->log_file, "/var/log/messages");
    }
    strncpy(conf->log_file, log_file, 256);
    
    /* read redis server configure */
    const char *redis_host;
    int redis_port;
    const char *redis_password;
    int redis_db;

    if (!config_lookup_string(&cfg, "redis_host", &redis_host)) {
        conf->err = "error: config.c load_conf_init() redis_host parameter invalid\n";
        config_destroy(&cfg);
        return false;
    }
    strncpy(conf->redis.host, redis_host, 256);
    
    if (!config_lookup_int(&cfg, "redis_port", &redis_port)) {
        conf->err = "error: config.c load_conf_init() redis_port parameter invalid\n";
        config_destroy(&cfg);
        return false;
    }
    conf->redis.port = redis_port;
    
    if (!config_lookup_string(&cfg, "redis_password", &redis_password)) {
        conf->err = "error: config.c load_conf_init() redis_password parameter invalid\n";
        config_destroy(&cfg);
        return false;
    }
    strncpy(conf->redis.password, redis_password, 64);
    
    if (!config_lookup_int(&cfg, "redis_db", &redis_db)) {
        conf->err = "error: config.c load_conf_init() redis_db parameter invalid\n";
        config_destroy(&cfg);
        return false;
    }
    conf->redis.db = redis_db;

    /* read postgresql server configure */
    const char *pgsql;

    if (!config_lookup_string(&cfg, "pgsql", &pgsql)) {
        conf->err = "error: config.c load_conf_init() pgsql parameter invalid\n";
        config_destroy(&cfg);
        return false;
    }
    strncpy(conf->pgsql, pgsql, 128);

    /* read freeswitch event socket configuration */
    const char *esl_host;
    int esl_port;
    const char *esl_password;
    
    if (!config_lookup_string(&cfg, "esl_host", &esl_host)) {
        conf->err = "error: config.c load_conf_init() esl_host parameter invalid\n";
        config_destroy(&cfg);
        return false;
    }
    strncpy(conf->esl.host, esl_host, 128);

    if (!config_lookup_int(&cfg, "esl_port", &esl_port)) {
        conf->err = "error: config.c load_conf_init() esl_port parameter invalid\n";
        config_destroy(&cfg);
        return false;
    }
    conf->esl.port = esl_port;
    
    if (!config_lookup_string(&cfg, "esl_password", &esl_password)) {
        conf->err = "error: config.c load_conf_init() esl_password parameter invalid\n";
        config_destroy(&cfg);
        return false;
    }
    strncpy(conf->esl.password, esl_password, 64);
    
    config_destroy(&cfg);
    return true;
}

