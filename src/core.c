/*
  TenJin Call Center System v3.0 Copyright (C) 2016 By TenJin Inc.
  by QQ:2403378726
*/

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <stdbool.h>
#include <ctype.h>
#include <unistd.h>
#include <time.h>
#include <hiredis/hiredis.h>
#include "config.h"
#include "core.h"
#include "data.h"

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

bool check_redis_connect(redisContext *db) {
    if (db == NULL) {
        return false;
    }

    bool r = false;
    redisReply *reply;
    reply = redisCommand(db, "PING");
    if (reply != NULL) {
        if (strcmp(reply->str, "PONG") == 0) {
            r = true;
        }
        freeReplyObject(reply);
    }

    return r;
}

// check invalid number
bool is_number(char *number, size_t len) {
    if ((number == NULL) || ((len < 8) || (len > 12))) {
        return false;
    }
    
    int i;
    for (i = 0; i < len; i++) {
        if (!isdigit(number[i])) {
            return false;
        }
    }

    return true;
}

// check is phone
bool is_phone(const char *number, size_t len) {
    if ((number == NULL) || (*number == '\0') || (len != 11)) return false;

    if (number[0] != '1') return false;
    if (number[1] < '3' || number[1] > '8') return false;
    if (number[2] < '0' || number[2] > '9') return false;

    return true;
}

// check is local number
bool is_local(const char *number) {
    if ((number == NULL) || (*number == '\0')) return false;
    
    char prefix[7];
    prefix[0] = number[0];
    prefix[1] = number[1];
    prefix[2] = number[2];
    prefix[3] = number[3];
    prefix[4] = number[4];
    prefix[5] = number[5];
    prefix[6] = number[6];
    int num = atoi(prefix);
    int len = sizeof(local) / sizeof(local[0]);
    for (int i = 0; i < len; i++) {
        if (local[i] == num) return true;
    }
    return false;
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

