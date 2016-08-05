
#ifndef _CONFIG_H
#define _CONFIG_H

#include <stdbool.h>

typedef struct {
    char host[64];
    int port;
    char password[64];
    int db;
} redis_t;

typedef struct {
    char host[64];
    int port;
    char password[64];
} esl_t;

typedef struct {
    redis_t redis;
    char pgsql[256];
    esl_t esl;
    char log_file[256];
    char *err;
} conf_t;

bool load_conf_init(const char *file, conf_t *conf);

#endif
