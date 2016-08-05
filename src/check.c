#include <stdio.h>
#include <stdlib.h>
#include <stdbool.h>
#include <string.h>
#include <unistd.h>
#include <fcntl.h>
#include <sys/stat.h>
#include <mqueue.h>
#include <hiredis/hiredis.h>
#include <libconfig.h>
#include <esl.h>
#include "check.h"

static conf_t conf;
static char *err = NULL;
static bool backgdMode = false;

int main(int argc, char *argv[]) {
    int opt;
    int thr = 3;
    int expired = 7;
    
    /* parse command line options */
    while ((opt = getopt(argc, argv, "c:de:ht:v")) != -1) {
        switch (opt) {
        case 'c':
            if (loadCfg(optarg) == -1) {
                fprintf(stderr, "Error: %s\n", err);
                return -1;
            }
            break;
        case 'e':
            expired = optarg ? atoi(optarg) : 0;
            if (expired > 30 || expired < 1) {
                fprintf(stderr, "Error: expire time range 1 to 30 days\n");
                return -1;
            }
            conf.expired = expired;
            break;
        case 'd':
            backgdMode = true;
            break;
        case 'h':
            usage(argv[0]);
            return 0;
        case 't':
            thr = optarg ? atoi(optarg) : 0;
            if (thr > 8 || thr < 1) {
                fprintf(stderr, "Error: thread count range 1 to 8\n");
                return -1;
            }
            break;
        case 'v':
            fprintf(stdout, "lemon version 1.0\n");
            fprintf(stdout, "Copyright (c) 2016 the lemon group\n");
            return 0;
        default:
            usage(argv[0]);
            return -1;
        }
    }
    /* check background working mode */
    if (backgdMode) {
        signal(SIGCHLD, SIG_IGN);
        daemon(0, 0);
    }
    
    struct mq_attr attr;
    attr.mq_maxmsg = 8192;
    attr.mq_msgsize = sizeof(number_t);
    mqd_t mqid = mq_open("/equeue", O_WRONLY | O_CREAT, 0666, &attr);
    if (mqid == -1) {
        if (backgdMode) {
            logs(conf.logFile, "Error: Can not open or create mqueue");
        } else {
            fprintf(stderr, "Error: Can not open or create mqeueu error\n");
        }
        return -1;
    }

    /* start working thread */
    int i;
    pthread_t pid;
    for (i = 0; i < thr; i++) {
        pthread_create(&pid, NULL, the_process, NULL);
    }

    esl_handle_t handle = {{0}};
    esl_status_t status;
    esl_connect(&handle, conf.esl.host, conf.esl.port, NULL, conf.esl.password);
    esl_events(&handle, ESL_EVENT_TYPE_PLAIN, "CHANNEL_HANGUP");
    esl_filter(&handle, "Hangup-Cause", "NORMAL_TEMPORARY_FAILURE");
    
    while((status = esl_recv(&handle)) == ESL_SUCCESS) {
        char *number = esl_event_get_header(handle.last_ievent, "Caller-Destination-Number");
        char *status = esl_event_get_header(handle.last_ievent, "Hangup-Cause");

        number_t data = {{0}};
        if (number && status) {
            /* check number length */
            if (strlen(number) < 11) {
                continue;
            }
            
            if (*number == '0') {
                strncpy(data.number, number + 1, 12);
            } else {
                strncpy(data.number, number, 12);
            }

            strncpy(data.status, status, 31);

            int n = mq_send(mqid, (const char *)&data, sizeof(number_t), 0);
            if (n == -1) {
                if (backgdMode) {
                    logs(conf.logFile, "Error: Can not write queue message");
                } else {
                    fprintf(stderr, "Error: Can not write queue message");
                }
            }
        }
    }

    esl_disconnect(&handle);

    return 0;
}

void *the_process(void *data) {
    mqd_t mqid = mq_open("/equeue", O_RDONLY);

    if (mqid == -1) {
        if (backgdMode) {
            logs(conf.logFile, "pthread can not open queue message");
        } else {
            fprintf(stderr, "-[%ld]-> pthread can not open queue message\n", pthread_self());
        }
        return;
    }

    number_t *e = (number_t *)malloc(sizeof(number_t));
    unsigned int n;

    struct mq_attr attr;
    if (mq_getattr(mqid, &attr) == -1) {
        if (backgdMode) {
            logs(conf.logFile, "pthread mq_getattr error");
        } else {
            fprintf(stderr, "-[%ld]-> pthread mq_getattr error\n", pthread_self());
        }
        return;
    }

    redisContext *db = NULL;
    redisReply *reply = NULL;
    db = redis(conf.redis.host, conf.redis.port, conf.redis.password, conf.redis.db);
    
    while (1) {
        n = mq_receive(mqid, (char *)e, attr.mq_msgsize, 0);
        if (n == -1) {continue;}

        if (e->number && e->status) {
            reply = redisCommand(db, "SET %s %u", e->number, time((time_t*)NULL));

            /* free reply object */
            if (reply != NULL) {
                freeReplyObject(reply);
            }

            unsigned int expire_time = conf.expired * 86400;
            reply = redisCommand(db, "EXPIRE %s %u", e->number, expire_time);

            /* check redis error */
            if (db->err & (REDIS_ERR_IO | REDIS_ERR_EOF)) {
                if (backgdMode) {
                    logs(conf.logFile, "pthread write number to redis error");
                } else {
                    fprintf(stdout, "-[%ld]-> write number: %-12s status: %s FAIL\n", pthread_self(), e->number, e->status);
                }
                redisFree(db);
                db = redis(conf.redis.host, conf.redis.port, conf.redis.password, conf.redis.db);
            } else {
                if (!backgdMode) {
                    fprintf(stdout, "-[%ld]-> write number: %-12s status: %s OK\n", pthread_self(), e->number, e->status);
                }
            }
            
            if (reply != NULL) {
                freeReplyObject(reply);
            }
        }
    }

    return;
}

void usage(const char *arg) {
    fprintf(stderr, "Usage: %s [options]\n", arg);
    fprintf(stderr, "\n");
    fprintf(stderr, "  -c <file>     the configure file\n");
    fprintf(stderr, "  -e <expired>  the maximum number of days overdue\n");
    fprintf(stderr, "  -t <thread>   working queue thread count\n");
    fprintf(stderr, "  -m <mode>     program run mode\n");
    fprintf(stderr, "  -v            show program version\n");
    fprintf(stderr, "\n");
    return;
}

int loadCfg(const char *file) {
    if (!file || *file == '\0') {
        err = "Invalid configure file name";
        goto err;
    }
    
    config_t cfg;
    config_init(&cfg);
    
    if (!config_read_file(&cfg, file)) {
        err = "Can not open configure file";
        goto err;
    }

    /* log file configure */
    const char *logFile;
    if (config_lookup_string(&cfg, "logFile", &logFile)) {
        strncpy(conf.logFile, logFile, 127);
    } else {
        strcpy(conf.logFile, "/var/log/messages");
    }
    
    
    /* redis configure */
    const char *redisHost;
    int redisPort;
    const char *redisPassword;
    int dbname;

    if (!config_lookup_string(&cfg, "redis_host", &redisHost)) {
        err = "Invalid redis_host parameter value\n";
        goto err;
    }
    strncpy(conf.redis.host, redisHost, 31);
    
    if (!config_lookup_int(&cfg, "redis_port", &redisPort)) {
        err = "Invalid redis_port parameter value\n";
        goto err;
    }
    conf.redis.port = redisPort;
    
    if (!config_lookup_string(&cfg, "redis_password", &redisPassword)) {
        err = "Invalid redis_password parameter value\n";
        goto err;
    }
    strncpy(conf.redis.password, redisPassword, 63);
    
    if (!config_lookup_int(&cfg, "redis_db", &dbname)) {
        err = "Invalid redis_db parameter value\n";
        goto err;
    }
    conf.redis.db = dbname;

    /* freeswitch event socket configuration */
    const char *eslHost;
    int eslPort;
    const char *eslPassword;
    
    if (!config_lookup_string(&cfg, "esl_host", &eslHost)) {
        err = "Invalid esl_host parameter value\n";
        goto err;
    }
    strncpy(conf.esl.host, eslHost, 63);

    if (!config_lookup_int(&cfg, "esl_port", &eslPort)) {
        err = "Invalid esl_port parameter value\n";
        goto err;
    }
    conf.esl.port = eslPort;
    
    if (!config_lookup_string(&cfg, "esl_password", &eslPassword)) {
        err = "Invalid esl_password parameter value\n";
        goto err;
    }
    strncpy(conf.esl.password, eslPassword, 63);

    config_destroy(&cfg);
    return 0;
err:
    config_destroy(&cfg);
    return -1;
}

// create a redis database connection
redisContext *redis(char *host, unsigned short port, char *password, unsigned int db) {
    if (host == NULL) return NULL; 
    // init redis database
    redisContext *c = NULL;
    redisReply *reply = NULL;
    struct timeval timeout = {1, 500000};
    
    // connection to redis database
    c = redisConnectWithTimeout(host, port, timeout);
    if (c == NULL || c->err) {
        if (c) redisFree(c);
        return NULL;
    }

    // auth password
    if (password) {
        reply = redisCommand(c, "AUTH %s", password);
        if (reply != NULL) freeReplyObject(reply);
    }
    
    // select database
    reply = redisCommand(c, "SELECT %d", db);
    if (reply != NULL) {
        if ((reply->type == REDIS_REPLY_STATUS) && (strcmp(reply->str, "OK") == 0)) {
            freeReplyObject(reply);
            return c;
        }
        freeReplyObject(reply);
    }
    redisFree(c);
    return NULL;
}

// write log to file
void logs(const char *file, const char *messages) {
    if (file == NULL || messages == NULL) {
        return;
    }
    
    time_t rawtime;
    struct tm *t;
    time(&rawtime);
    t = localtime(&rawtime);
    
    FILE *fp = NULL;
    fp = fopen(file, "a");
    if (fp != NULL) {
        fprintf (fp, "[%4d-%02d-%02d %02d:%02d:%02d] %s\n",
                 t->tm_year + 1900, t->tm_mon + 1, t->tm_mday,
                 t->tm_hour, t->tm_min, t->tm_sec, messages);
        fclose(fp);
    }
    return;
}
