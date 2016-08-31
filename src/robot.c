/*
  TenJin Call Center System v3.0 Copyright (C) 2016 By TenJin Inc.
  By QQ: 2403378726
*/

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <stdbool.h>
#include <unistd.h>
#include <signal.h>
#include <esl.h>
#include <libpq-fe.h>
#include <hiredis/hiredis.h>
#include "core.h"
#include "config.h"
#include "robot.h"

global_t args;

int main(int argc, char *argv[]) {
    /* analysis of command line parameters */
    args.daemon = 0;
    args.tran = 0;
    args.conf_file = "/etc/config.conf";
    args.company_id = 0;

    int opt = 0;
    char *optstring = "dtf:c:";
    opt = getopt(argc, argv, optstring);
    while (opt != -1) {
        switch (opt) {
        case 'd':
            args.daemon = 1;
            break;
        case 't':
            args.tran = 1;
        case 'f':
            args.conf_file = optarg;
            break;
        case 'c':
            args.company_id = atoi(optarg);
            break;
        }
        opt = getopt(argc, argv, optstring);
    }

    /* daemon mode */
    if (args.daemon) {
        signal(SIGCHLD, SIG_IGN);
        daemon(0, 0);
    }

    /* write pid to file */
    log_pid(args.company_id);
    
    /* load configure file */
    conf_t conf;

    if (!load_conf_init(args.conf_file, &conf)) {
        logs("/var/log/messages", conf.err);
        return EXIT_FAILURE;
    }

    company_t company = {0, 0, 0};
    task_t task = {0, 0, 0, 0, 0};
    sound_t sound = {0, ""};

    /* initialize postgresql database connection */
    PGconn *conn = NULL;
    conn = PQconnectdb(conf.pgsql);

    //esl_connect(&esl, "127.0.0.1", 8021, NULL, "ClueCon");
    //printf("esl_host: %s, esl_port: %d, esl_password: %s\n", conf.esl.host, conf.esl.port, conf.esl.password);

    /* initialize redis database handle */
    redisContext *db = NULL;
    
    while (true) {
        /* initialize redis database connection */
        db = redis(conf.redis.host, conf.redis.port, NULL, conf.redis.db);

        /* check company exist */
        if (!is_company_exist(db, args.company_id)) {
            logs("/var/log/tenjin.log", "error: main() the company id is not exist");
            return EXIT_FAILURE;
        }
        
        /* initialization working */
        if (work_init(db, &args, &company, &task, &sound) == -1) {
            logs("/var/log/tenjin.log", "error: main() work initialize failed");
            goto skip;
        }

        /* check postgresql connection status */
        if (PQstatus(conn) != CONNECTION_OK) {
            conn = PQconnectdb(conf.pgsql);
            logs("/var/log/tenjin.log", "server.c main(): connection to postgresql failed");
            goto skip;
        }
    
        /* check task type */
        switch (task.type) {
        case AUTO_MODE:
            work_type_auto(conn, db, &conf, &company, &task);
            break;
        case FIXED_MODE:
            work_type_fixed(conn, db, &conf, &company, &task);
            break;
        case MANUAL_MODE:
            work_type_manual();
            break;
        case SOUND_MODE:
            work_type_sound();
            break;
        }

    skip:
        /* cleanup redis connection */
        if(db != NULL) {
            redisFree(db);
            db = NULL;
        }

        /* cleanup buffer data */
        memset(&company, 0, sizeof(company_t));
        memset(&task, 0, sizeof(task_t));
        memset(&sound, 0, sizeof(sound_t));

        usleep(300000);
    }
    
    return 0;
}

/* fetch company information */
int get_company(redisContext *db, int company_id, company_t *company) {
    if (!db || company_id < 1 || !company) {
        return -1;
    }

    int r = -1;
    
    redisReply *reply = NULL;
    
    reply = redisCommand(db, "HMGET company.%d task concurrent", company_id);
    if (reply != NULL) {
        if (reply->type == REDIS_REPLY_ARRAY) {
            company->id = company_id;
            company->task = reply->element[0]->str ? atoi(reply->element[0]->str) : 0;
            company->concurrent = reply->element[1]->str ? atoi(reply->element[1]->str) : 0;
            r = 0;
            /* debug code */
            /*
              printf("reply->type: %d\n", reply->type);
              printf("reply->integer: %lld\n", reply->integer);
              printf("reply->len: %d\n", reply->len);
              printf("reply->str: %s\n", reply->str);
              printf("reply->elements: %zd\n", reply->elements);
              for (int i = 0; i < reply->elements; i++) {
              printf("element[%d]: %s\n", i, reply->element[i]->str);
              }
            */
            
        }
        freeReplyObject(reply);
    }

    return r;
    
}

/* check company exist */
bool is_company_exist(redisContext *db, int company_id) {
    if (!db || company_id < 1) {
        return false;
    }

    bool r = false;
    redisReply *reply = NULL;
    
    reply = redisCommand(db, "EXISTS company.%d", company_id);
    
    if (reply != NULL) {
        if (reply->type == REDIS_REPLY_INTEGER) {
            if (reply->integer == 1) {
                r = true;
            }
        }
        freeReplyObject(reply);
    }

    return r;
}

/* check task exist */
bool is_task_exist(redisContext *db, int task_id) {
    if (!db || task_id < 1) {
        return false;
    }

    bool r = false;
    redisReply *reply = NULL;
    
    reply = redisCommand(db, "EXISTS task.%d", task_id);
    
    if (reply != NULL) {
        if (reply->type == REDIS_REPLY_INTEGER) {
            if (reply->integer == 1) {
                r = true;
            }
        }
        freeReplyObject(reply);
    }

    return r;
}

/* fetch task information */
int get_task(redisContext *db, int task_id, task_t *task) {
    if (!db || task_id < 1 || !task) {
        return -1;
    }

    int r = -1;

    redisReply *reply = NULL;
    
    reply = redisCommand(db, "HMGET task.%d type dial play sound presskey", task_id);
    if (reply != NULL) {
        if (reply->type == REDIS_REPLY_ARRAY) {
            task->id = task_id;
            task->type = reply->element[0]->str ? atoi(reply->element[0]->str) : -1;
            task->dial = reply->element[1]->str ? atoi(reply->element[1]->str) : 0;
            task->play = reply->element[2]->str ? atoi(reply->element[2]->str) : 0;
            task->sound = reply->element[3]->str ? atoi(reply->element[3]->str) : 0;
            if (task->sound < 1) {task->play = 0;}
            task->presskey = reply->element[4]->str ? atoi(reply->element[4]->str) : 0;
            r = 0;
        }
        freeReplyObject(reply);
    }

    return r;
}

/* check sound exist */
bool is_sound_exist(redisContext *db, int sound_id) {
    if (!db || sound_id < 1) {
        return false;
    }

    bool r = false;
    redisReply *reply = NULL;
    
    reply = redisCommand(db, "EXISTS sound.%d", sound_id);
    
    if (reply != NULL) {
        if (reply->type == REDIS_REPLY_INTEGER) {
            if (reply->integer == 1) {
                r = true;
            }
        }
        freeReplyObject(reply);
    }

    return r;
}

/* fetch sound information */
int get_sound(redisContext *db, int sound_id, sound_t *sound) {
    if (!db || sound_id < 1 || !sound) {
        return -1;
    }

    int r = -1;
    redisReply *reply = NULL;
    
    reply = redisCommand(db, "HMGET sound.%d file", sound_id);
    
    if (reply != NULL) {
        if (reply->type == REDIS_REPLY_ARRAY) {
            sound->id = sound_id;
            if (reply->element[0]->str) {
                strcpy(sound->file, reply->element[0]->str);
                r = 0;
            }
        }
        freeReplyObject(reply);
    }
    
    return r;
}

/* initialization working function */
bool work_init(redisContext *db, global_t *args, company_t *company, task_t *task, sound_t *sound) {
    if (!db || !args) {
        return -1;
    }

    if(get_company(db, args->company_id, company) == -1) {
        return -1;
    }

    if (company->task < 1) {
        return -1;
    }
        
    if (get_task(db, company->task, task) == -1) {
        return -1;
    }

    if (task->play == 1) {
        if (get_sound(db, task->sound, sound) == -1) {
            return -1;
        }
    }

    return 0;
}

int get_company_current_concurrent(PGconn *conn, int company_id) {
    if (company_id < 1) {
        return -1;
    }

    PGresult *res = NULL;

    char sql[512];
    sprintf(sql, "select count(cid_num) from channels where cid_num = '%d'", company_id);

    res = PQexec(conn, sql);
    if (PQresultStatus(res) != PGRES_TUPLES_OK) {
        PQclear(res);
        return -1;      
    }

    int concurrent;
    concurrent = atoi(PQgetvalue(res, 0, 0));
    PQclear(res);
    
    return concurrent;
}

int get_queue_summary(PGconn *conn, int company_id, int *concurrent, int *login, int *idle) {
    if (!conn || company_id < 1) {
        return -1;
    }

    PGresult *res = NULL;
    
    char sql[4096];
    sprintf(sql, "select count(cid_num) from channels where cid_num = '%d' union all select count(status) from agents where name in(select agent from tiers where queue='%d@queue') and status = 'Available' union all select count(state) from agents where name in(select agent from tiers where queue='%d@queue') and status = 'Available' and state = 'Waiting';", company_id, company_id, company_id);

    res = PQexec(conn, sql);
    if (PQresultStatus(res) != PGRES_TUPLES_OK) {
        PQclear(res);
        return -1;
    }

    *concurrent = atoi(PQgetvalue(res, 0, 0));
    *login = atoi(PQgetvalue(res, 1, 0));
    *idle = atoi(PQgetvalue(res, 2, 0));
    
    PQclear(res);

    return 0;
}

int get_number(redisContext *db, int task_id, number_t *number, int num) {
    if (!db || task_id < 1 || num < 1) {
        return 0;
    }

    int n = 0;
    
    redisReply *reply = NULL;
    int i;

    if (num > MAX_NUMBER_LEN) {
        num = MAX_NUMBER_LEN;
    }

    for (i = 0; i < num; i++) {
        reply = redisCommand(db, "LPOP data.%d", task_id);
        if (reply != NULL) {
            if (reply->type == REDIS_REPLY_STRING) {
                if (reply->str) {
                    strcpy(number->called[i], reply->str);
                    n++;
                }
            }
            freeReplyObject(reply);
        }
    }
    
    return n;
}

void number_prefix_process(number_t *number, int size) {
    if (!number || size < 1) {
        return;
    }

    int i;
    for (i = 0; i < size; i++) {
        if (!is_local(number->called[i])) {
            char buff[16] = "0";
            strncat(buff, number->called[i], sizeof(buff) - 1);
            strcpy(number->called[i], buff);
        }
    }

    return;
}

void originate(esl_handle_t *esl, int company_id, int count, number_t *number) {
    if (!esl || !number) {
        return;
    }

    int i;
    for (i = 0; i < count; i++) {
        char cmd[512];
        sprintf(cmd, "bgapi originate {accountcode=%d,ignore_early_media=true,originate_timeout=30}sofia/gateway/trunk.%d.gw/%s service XML callcenter robot %d\n\n",
                company_id, company_id, number->called[i], company_id);
        esl_send(esl, cmd);
        usleep(50000);
    }

    return;
}

int work_type_auto(PGconn *conn, redisContext *db, conf_t *conf, company_t *company, task_t *task) {
    int concurrent = 0;
    int login = 0;
    int idle = 0;
    
    if (get_queue_summary(conn, company->id, &concurrent, &login, &idle) == -1) {
        logs("/var/log/tenjin.log", "error: work_type_auto() get queue summary failed");
        sleep(1);
        return -1;
    }

    if (concurrent >= company->concurrent) {
        return 0;
    }

    int num = 0;
    if (login > 0) {
        if (login < 5) {
            num =  (login * task->dial) - concurrent;
        } else {
            num = ((idle * task->dial) + (login - idle) + task->dial) - concurrent;
        }
    } else {
        return 0;
    }

    int count = 0;
    number_t number;
    memset(&number, 0, sizeof(number_t));
    
    if (num > 0) {
        count = get_number(db, task->id, &number, num);
        if (count > 0) {
            /* number rewriting processing */
            if (args.tran) {
                number_prefix_process(&number, count);
            }
            
            /* initialize event socket connection */
            esl_handle_t esl = {{0}};
            esl_connect(&esl, conf->esl.host, conf->esl.port, NULL, conf->esl.password);
            originate(&esl, company->id, count, &number);
            usleep(100000);
            esl_disconnect(&esl);
        } else {
            sleep(3);
            return 0;
        }
    }

    /* printf("concurrent: %d, login: %d, idle: %d, dial: %d, task.id: %d, task.sound: %d num: %d\n", concurrent, login, idle, task->dial, task->id, task->sound, num); */

    /*
      int i;
      for (i = 0; i < count; i++) {
      printf("number[%d] -> %s\n", i, number.called[i]);
      }
    */
    
    return 0;
}

int work_type_fixed(PGconn *conn, redisContext *db, conf_t *conf, company_t *company, task_t *task) {
    int concurrent;

    concurrent = get_company_current_concurrent(conn, company->id);
    if (concurrent == -1) {
        logs("/var/log/tenjin.log", "error: work_type_fixed() get current concurrent failed");
        sleep(1);
        return -1;
    }
    
    if (concurrent >= company->concurrent) {
        return 0;
    }
    
    int num = 0;
    num = task->dial - concurrent;

    int count = 0;
    number_t number;
    memset(&number, 0, sizeof(number_t));
    
    if (num > 0) {
        count = get_number(db, task->id, &number, num);
        if (count > 0) {
            /* number rewriting processing */
            number_prefix_process(&number, count);

            /* initialize event socket connection */
            esl_handle_t esl = {{0}};
            esl_connect(&esl, conf->esl.host, conf->esl.port, NULL, conf->esl.password);
            originate(&esl, company->id, count, &number);
            esl_disconnect(&esl);
        } else {
            sleep(3);
            return 0;
        }
    }

    /* printf("concurrent: %d, dial: %d, task: %d, num: %d\n", concurrent, task->dial, task->id, num); */

    /*
      int i;
      for (i = 0; i < count; i++) {
      printf("number[%d] -> %s\n", i, number.called[i]);
      }
    */
    
    return 0;
}

int work_type_manual(void) {
    logs("/var/log/tenjin.log", "current run in manual mode!");
    sleep(3);
    return 0;
}

int work_type_sound(void) {
    logs("/var/log/tenjin.log", "current run in play sound mode!");
    sleep(3);
    return 0;
}

void log_pid(int company_id) {
    FILE *fp = NULL;
    char pid_file[512] = "";
    sprintf(pid_file, "/var/service/%d.pid", company_id);
    fp = fopen(pid_file, "w");
    if (fp != NULL) {
        fprintf (fp, "%d", getpid());
        fclose(fp);
    }
}

