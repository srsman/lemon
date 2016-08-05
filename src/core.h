/*
  TenJin Call Center System v3.0 Copyright (C) 2016 By TenJin Inc.
  by QQ:2403378726
*/

#ifndef _CORE_H
#define _CORE_H

#include <stdbool.h>
#include <hiredis/hiredis.h>

redisContext *redis(char *host, unsigned short port, char *password, unsigned int db);
bool check_redis_connect(redisContext *db);
bool is_phone(const char *number, size_t len);
bool is_number(char *number, size_t len);
bool is_local(const char *number);
void logs(const char *file, const char *messages);

#endif
