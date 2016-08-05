
#ifndef _ROBOT_H
#define _ROBOT_H

#include <esl.h>
#include <libpq-fe.h>
#include <hiredis/hiredis.h>

#define AUTO_MODE   1
#define FIXED_MODE  2
#define MANUAL_MODE 3
#define SOUND_MODE  4

#define MAX_NUMBER_LEN 24

typedef struct {
    int daemon;
    char *conf_file;
    int company_id;
} global_t;

typedef struct {
    int id;
    int task;
    int concurrent;
} company_t;

typedef struct {
    int id;
    int type;
    int dial;
    int play;
    int sound;
    int presskey;
} task_t;

typedef struct {
    int id;
    char file[128];
} sound_t;

typedef struct {
    char called[MAX_NUMBER_LEN][16];
} number_t;

bool is_company_exist(redisContext *db, int company_id);
int get_company(redisContext *db, int company_id, company_t *company);
bool is_task_exist(redisContext *db, int task_id);
int get_task(redisContext *db, int task_id, task_t *task);
bool is_sound_exist(redisContext *db, int sound_id);
int get_sound(redisContext *db, int sound_id, sound_t *sound);
bool work_init(redisContext *db, global_t *args, company_t *company, task_t *task, sound_t *sound);
int get_company_current_concurrent(PGconn *conn, int company_id);
int get_queue_summary(PGconn *conn, int company_id, int *concurrent, int *login, int *idle);
int get_number(redisContext *db, int task_id, number_t *number, int num);
void number_prefix_process(number_t *number, int size);
void originate(esl_handle_t *esl, int company_id, int count, number_t *number);
int work_type_auto(PGconn *conn, redisContext *db, conf_t *conf, company_t *company, task_t *task);
int work_type_fixed(PGconn *conn, redisContext *db, conf_t *conf, company_t *company, task_t *task);
int work_type_manual(void);
int work_type_sound(void);
void log_pid(int company_id);

#endif

