<?php

use Phalcon\Loader;
use Phalcon\Mvc\Micro;

require_once '../config.php';

$loader = new Loader();

$loader->registerDirs(
    array(
        '../app/models/'
    )
)->register();

$app = new Micro();

$app['redis'] = function () {
    $redis = new Redis();
    $redis->connect(REDIS_HOST, REDIS_PORT);
    $redis->select(REDIS_DB);
    return $redis;
};

$app->get('/agent/{uid}/status', function ($uid) use($app) {
    $u = new User($app);
    $user = $u->get($uid);
    if ($user != null) {
        $status = intval($user['status']);
        echo $status;
    } else {
        echo '0';
    }

    return true;
});

$app->get('/check/{number}', function ($number) {
    require_once '../data.php';
    $number = str_replace(' ', '', $number);
    $number_prefix = intval(substr($number, 0, 7));
    if (in_array($number_prefix, $local, true)) {
        echo $number;
    } else {
        echo 0,$number;
    }

    return true;
});

$app->get('/company/{company_id}/power', function ($company_id) use ($app) {
    $c = new Company($app);
    $company = $c->get($company_id);
    if ($company != null) {
        $task_id = intval($company['task']);
        if ($task_id > 0) {
	    echo '1';
	    return true;
	}
    }

    echo '0';
    return true;
});

$app->get('/agent/{uid}/outcall', function ($uid) use($app) {
    $u = new User($app);
    $user = $u->get($uid);
    if ($user != null) {
        if ($user['status'] == '1' && $user['calls'] == '1') {
	    echo '1';
	    return true;
	}
    }

    echo '0';
    return true;
});

$app->get('/autocall/{company_id}/{uid}', function ($company_id, $uid) use ($app) {
    $c = new Company($app);
    $company = $c->get($company_id);
    if ($company != null) {
        $task_id = intval($company['task']);
        $t = new Task($app);
        $task = $t->get($task_id);
        if ($task != null) {
            if ($task['type'] === '3') {
                $number = $t->get_number($task_id);
                $u = new User($app);
                $u->SetLastCalled($uid, $number);
                echo $number;
                return true;
            }
        }
    }

    echo 'unknown';
    return true;
});

$app->get('/agent/{uid}/lastcalled', function ($uid) use($app) {
    $u = new User($app);
    $user = $u->get($uid);
    if ($user != null) {
        echo $user['last_called'];
    } else {
        echo 'unknown';
    }

    return true;
});

$app->get('/data/{company_id}/answer', function ($company_id) use($app) {
    $c = new Company($app);
    $company = $c->get($company_id);
    if ($company != null) {
        $task_id = intval($company['task']);
	if ($task_id > 0) {
	    $t = new Task($app);
            $t->answer($task_id);
	}
        
    }

    return true;
});

$app->get('/data/{company_id}/complete', function ($company_id) use($app) {
    $c = new Company($app);
    $company = $c->get($company_id);
    if ($company != null) {
        $task_id = $company['task'];
        $t = new Task($app);
        $t->complete($task_id);
    }

    return true;
});

$app->get('/task/{company_id}/sound', function ($company_id) use($app) {
    $c = new Company($app);
    $company = $c->get($company_id);
    if ($company != null) {
        $task_id = intval($company['task']);
	if ($task_id > 0) {
            $t = new Task($app);
            $task = $t->get($task_id);
	    if ($task != null && $task['play'] == '1') {
	        $s = new Sound($app);
                $sound = $s->get($task['sound']);
                if ($sound != null && $sound['status'] == '1') {
                    echo $sound['file'];
                    return true;
                }
	    }
	}
    }

    echo 'null';
    return true;
});

$app->notFound(function() {
    return true;
});

$app->handle();
