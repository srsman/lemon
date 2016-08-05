/*
  Lemon empty number filter system
  by qq 2403378726
*/

#ifndef _CHECK_H
#define _CHECK_H

#include <hiredis/hiredis.h>

void usage(const char *arg);
void *the_process(void *data);
int loadCfg(const char *file);
redisContext *redis(char *host, unsigned short port, char *password, unsigned int db);
void logs(const char *file, const char *messages);

typedef struct {
    char host[32];
    unsigned short port;
    char password[64];
    int db;
} rdscfg_t;

typedef struct {
    char host[32];
    unsigned short port;
    char password[64];
} eslcfg_t;

typedef struct {
    int expired;
    eslcfg_t esl;
    rdscfg_t redis;
    char logFile[128];
} conf_t;
    
typedef struct {
    char number[16];
    char status[32];
} number_t;

#endif
