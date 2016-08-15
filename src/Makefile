CC = gcc
CFLAGS = -std=gnu99 -Wall -pedantic
INCLUDE = -I /usr/include -I /usr/local/include
BASE = /root/lemon
ESLPATH = $(BASE)/freeswitch-1.6.9/libs/esl

all: upd robot check

upd: upd.c core.c core.h config.h
	$(CC) $(CFLAGS) $(INCLUDE) -lhiredis upd.c core.c -o upd

robot: robot.c core.c core.h config.c config.h data.h $(ESLPATH)/.libs/libesl.a
	$(CC) $(CFLAGS) $(INCLUDE) -I $(ESLPATH)/src/include -lm -pthread -lconfig -lhiredis -lpq robot.c core.c config.c $(ESLPATH)/.libs/libesl.a -o robot

check: check.c check.h $(ESLPATH)/.libs/libesl.a
	gcc -I $(ESLPATH)/src/include -lrt -lm -pthread -lconfig -lhiredis check.c $(ESLPATH)/.libs/libesl.a -o check

test: test.c config.c config.h $(ESLPATH)/.libs/libesl.a
	gcc -I $(ESLPATH)/src/include -lm -pthread -lconfig test.c config.c $(ESLPATH)/.libs/libesl.a -o test

install:
	/bin/cp -vf robot /usr/bin
	/usr/bin/mkdir -pv /var/www
	/bin/cp -vf upd /var/www
	/bin/cp -vf config.conf /etc
	/bin/chown -R apache:apache /var/www

clean:
	rm -rf upd robot
