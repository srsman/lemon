<?php

/* redis database configure */
define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);
define('REDIS_PASSWORD', NULL);
define('REDIS_DB', 0);

/* billing server configure */
define('RADIUS_HOST', '127.0.0.1');
define('RADIUS_PORT', 6379);
define('RADIUS_PASSWORD', NULL);
define('RADIUS_DB', 0);

/* core database */
define('CORE_HOST', '127.0.0.1');
define('CORE_PORT', 5432);
define('CORE_USER', 'postgres');
define('CORE_PASSWORD', 'postgres');
define('CORE_DB', 'postgres');

/* pbx database */
define('PBX_HOST', '127.0.0.1');
define('PBX_PORT', 5432);
define('PBX_USER', 'postgres');
define('PBX_PASSWORD', 'postgres');
define('PBX_DB', 'freeswitch');

/* cdr database */
define('CDR_HOST', '127.0.0.1');
define('CDR_PORT', 5432);
define('CDR_USER', 'postgres');
define('CDR_PASSWORD', 'postgres');
define('CDR_DB', 'postgres');

/* order database */
define('ORDER_HOST', '127.0.0.1');
define('ORDER_PORT', 5432);
define('ORDER_USER', 'postgres');
define('ORDER_PASSWORD', 'postgres');
define('ORDER_DB', 'postgres');

/* freeswitch esl configure */
define('ESL_HOST', '127.0.0.1');
define('ESL_PORT', '8021');
define('ESL_PASSWORD', 'ClueCon');

/* the secure key */
define('SECURE_KEY', 'HuAn4yigexg5qnAfcqtPdznbagqzutaK');

