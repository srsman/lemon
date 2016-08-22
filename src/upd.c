/*
  TenJin Call Center System v3.0 Copyright (C) 2016 By TenJin Inc.
  by QQ: 2403378726
*/

#include <stdio.h>
#include <stdlib.h>
#include <stdbool.h>
#include <string.h>
#include <ctype.h>
#include <unistd.h>
#include <fcntl.h>
#include <sys/stat.h>
#include <signal.h>
#include <hiredis/hiredis.h>
#include "core.h"

#define CHKDB_HOST "127.0.0.1"
#define CHKDB_PORT 6379
#define CHKDB_NAME 0

bool is_file_exist(const char *file);
bool is_task_exist(redisContext *db, int task);
bool checkNum(char *number, size_t len);
bool checkEmptyNum(redisContext *db, char *number);

int main(int argc, char *argv[]) {
    int opt;
    int task = 0;
    char *file = NULL;
    bool check = false;
    
    /* parse command line options */
    while ((opt = getopt(argc, argv, "ct:f:")) != -1) {
        switch (opt) {
        case 'c':
            check = true;
            break;
        case 't':
            task = optarg ? atoi(optarg) : 0;
            break;
        case 'f':
            file = optarg;
            break;
        default:
            return -1;
        }
    }

    /* daemon run mode */
    signal(SIGCHLD, SIG_IGN);
    daemon(0, 0);

    /* check data file */
    if (!is_file_exist(file)) {
        return -1;
    }

    /* local redis database */
    redisContext *db = redis("127.0.0.1", 6379, NULL, 0);
    if (!db) {
        return -1;
    }

    /* number data filter database */
    redisContext *chkdb = NULL;
    if (check) {
        chkdb = redis(CHKDB_HOST, CHKDB_PORT, NULL, CHKDB_NAME);
    }

    /* check task id */
    if (!is_task_exist(db, task)) {
        goto end;
    }
    
    /* file processing */
    FILE *fp = NULL;
    fp = fopen(file, "r");
    if (fp == NULL) {
        goto end;
    }

    int i = 0;
    char buff[1024] = {0};
    redisReply *reply = NULL;
    while (fgets(buff, 1024, fp) != NULL) {
        /* check number */
        if (checkNum(buff, sizeof(buff))) {
            /* check empty number */
            if (check && chkdb) {
                if (checkEmptyNum(chkdb, buff)) {
                    continue;
                }
            }

            /* write number to redis */
            reply = redisCommand(db, "LPUSH data.%d %s", task, buff);
            if (reply != NULL) {
                freeReplyObject(reply);
                reply = NULL;
                i++;
            }
        }
    }
    
    /* close temp file */
    fclose(fp);
    unlink(file);
    
    // write number total
    reply = redisCommand(db, "HSET task.%d total %d", task, i);
        
    if (reply != NULL) {
        freeReplyObject(reply);
    }

end:
    if (db) {
        redisFree(db);
    }

    if (chkdb) {
        redisFree(chkdb);
    }
    
    return 0;
}

bool is_task_exist(redisContext *db, int task) {
    if (!db || task < 1) {
        return false;
    }

    bool ret = false;
    redisReply *reply = NULL;

    reply = redisCommand(db, "EXISTS task.%d", task);
    if (reply != NULL) {
        if (reply->type == REDIS_REPLY_INTEGER) {
            if (reply->integer == 1) {
                ret = true;
            }
        }
        freeReplyObject(reply);
    }

    return ret;
}

bool is_file_exist(const char *file) {
    if (file == NULL || *file == '\0') {
        return false;
    }
    
    FILE *fp = NULL;
    if ((fp = fopen(file, "r")) != NULL) {
        fclose(fp);
        return true;
    }
    
    return false;
}

bool checkNum(char *number, size_t len) {
    if (number == NULL || *number == '\0' || *number == '\r' || *number == '\n') {
        return false;
    }

    int i;
    for (i = 0; i < len; i++) {
        if (isspace(number[i])) {
            break;
        }
    }
    number[i] = '\0';

    int s = strlen(number);
    if (s < 7 || s > 11) {
        return false;
    }
    
    for (i = 0; i < s; i++) {
        if (!isdigit(number[i])) {
            return false;
        }
    }

    return true;
}

bool checkEmptyNum(redisContext *db, char *number) {
    if (db == NULL || number == NULL || *number == '\0') {
        return false;
    }

    bool ret = false;
    redisReply *reply = NULL;
    
    reply = redisCommand(db, "EXISTS %s", number);
    if (reply != NULL) {
        if (reply->type == REDIS_REPLY_INTEGER) {
            if (reply->integer == 1) {
                ret = true;
            }
        }
        freeReplyObject(reply);
    }

    return ret;
}
