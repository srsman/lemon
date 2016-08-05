<?php

use Phalcon\Crypt;
use Phalcon\Loader;
use Phalcon\Mvc\View;
use Phalcon\Acl\Role;
use Phalcon\Mvc\Router;
use Phalcon\Dispatcher;
use Phalcon\Acl\Resource;
use Phalcon\Mvc\Application;
use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\Url as UrlProvider;
use Phalcon\Acl\Adapter\Memory as AclList;
use Phalcon\Mvc\Dispatcher as MvcDispatcher;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Mvc\Dispatcher\Exception as DispatchException;
use Phalcon\Flash\Direct as FlashDirect;
use Phalcon\Db\Adapter\Pdo\Postgresql;

try {
    /* load config file */
    require '../config.php';
    require "../app/controllers/ControllerBase.php";

    /* Register an autoloader */
    $loader = new Loader();
    $loader->registerDirs(array(
        '../app/controllers/',
        '../app/models/',
        '../app/library'
    ))->register();

    // Create a DI
    $di = new FactoryDefault();

    /* Setup router method */
    $di->set('router', function () {
        $router = new Router();
        $router->setUriSource(Router::URI_SOURCE_SERVER_REQUEST_URI);

        // Setup default router
        $router->add('/', ["controller" => 'index', "action" => 'index']);

        // Setup 404 not found page
        $router->notFound([
            'controller' => 'error',
            'action' => 'index'
        ]);

        return $router;
    });

    /* Setup 404 not found */
    $di->set('dispatcher', function () {
        $eventsManager = new EventsManager();
        $eventsManager->attach("dispatch:beforeException", function ($event, $dispatcher, $exception) {
            if ($exception instanceof DispatchException) {
                $dispatcher->forward([
                    'controller' => 'error',
                    'action'     => 'index'
                ]);

                return false;
            }

            switch ($exception->getCode()) {
            case Dispatcher::EXCEPTION_HANDLER_NOT_FOUND:
            case Dispatcher::EXCEPTION_ACTION_NOT_FOUND:
                $dispatcher->forward(
                    array(
                        'controller' => 'error',
                        'action'     => 'index'
                    )
                );

                return false;
            }
        });

        $dispatcher = new MvcDispatcher();
        $dispatcher->setEventsManager($eventsManager);
        return $dispatcher;
    }, true);
    
    /* setup acl list */
    $di->set('acl', function () {
        $acl = new AclList();
        $acl->setDefaultAction(Phalcon\Acl::DENY);

        $administrator = new Role("Administrator");
        $quality = new Role("Quality");
        $agent = new Role("Agent");

        $acl->addRole($administrator);
        $acl->addRole($quality);
        $acl->addRole($agent);

        $Status = new Resource('Status');
        $Agent = new Resource('Agent');
        $User = new Resource('User');
        $Task = new Resource('Task');
        $Exten = new Resource('Exten');
        $Order = new Resource('Order');
        $Product = new Resource('Product');
        $Sound = new Resource('Sound');
        $Cdr = new Resource('Cdr');
        $Help = new Resource('Help');
        $Account = new Resource('Account');
        $Api = new Resource('Api');

        $acl->addResource($Status, ['index']);
        $acl->addResource($Agent, ['index', 'order', 'todayOrder', 'getStatus', 'messages', 'getOrder', 'add', 'getCalled']);
        $acl->addResource($User, ['index', 'edit']);
        $acl->addResource($Task, ['index', 'start', 'stop', 'edit', 'update', 'create', 'delete']);
        $acl->addResource($Exten, ['index']);
        $acl->addResource($Order, ['index', 'edit', 'query', 'update']);
        $acl->addResource($Product, ['index', 'edit', 'create', 'update', 'delete']);
        $acl->addResource($Sound, ['index', 'create', 'update']);
        $acl->addResource($Cdr, ['index', 'query', 'report']);
        $acl->addResource($Help, ['index']);
        $acl->addResource($Account, ['index']);
        $acl->addResource($Api, ['user_get', 'user_update', 'sound_get', 'get_status', 'cdr_query', 'getRecord']);

        $acl->allow('Administrator', 'Status', ['index']);
        $acl->allow('Administrator', 'User', ['index', 'edit']);
        $acl->allow('Administrator', 'Task', ['index', 'start', 'stop', 'edit', 'update', 'create', 'delete']);
        $acl->allow('Administrator', 'Exten', ['index']);
        $acl->allow('Administrator', 'Order',  ['index', 'edit', 'query', 'update']);
        $acl->allow('Administrator', 'Product', ['index', 'edit', 'create', 'update', 'delete']);
        $acl->allow('Administrator', 'Sound', ['index', 'create', 'update']);
        $acl->allow('Administrator', 'Cdr', ['index', 'query', 'report']);
        $acl->allow('Administrator', 'Help', ['index']);
        $acl->allow('Administrator', 'Account', ['index']);
        $acl->allow('Administrator', 'Api', ['user_get', 'user_update', 'sound_get', 'get_status', 'cdr_query', 'getRecord']);

        $acl->allow('Quality', 'Status', ['index']);
        $acl->allow('Quality', 'Task', ['index']);
        $acl->allow('Quality', 'User', ['index']);
        $acl->allow('Quality', 'Exten', ['index']);
        $acl->allow('Quality', 'Order',  ['index', 'edit', 'query', 'update']);
        $acl->allow('Quality', 'Product', ['index']);
        $acl->allow('Quality', 'Sound', ['index']);
        $acl->allow('Quality', 'Cdr', ['index', 'query', 'report']);
        $acl->allow('Quality', 'Help', ['index']);
        $acl->allow('Quality', 'Api', ['user_get', 'sound_get', 'get_status', 'cdr_query', 'getRecord']);

        $acl->allow('Agent', "Agent", ['index', 'order', 'todayOrder', 'getStatus', 'messages', 'getOrder', 'add', 'getCalled']);

        return $acl;
    });

    /* Setup crypt key */
    $di->set('crypt', function () {
        $crypt = new Crypt();
        $crypt->setKey(SECURE_KEY);
        return $crypt;
    });

    
    /* redis database */
    $di->set('redis', function() {
        $redis = new Redis();
        $redis->connect(REDIS_HOST, REDIS_PORT);
        $redis->select(REDIS_DB);
        return $redis;
    });

    /* core database */
    $di->set('db', function () {
        return new Postgresql([
            "host"     => CORE_HOST,
            'port'     => CORE_PORT,
            "username" => CORE_USER,
            "password" => CORE_PASSWORD,
            "dbname"   => CORE_DB
        ]);
    });

    /* pbx database */
    $di->set('pbx', function () {
        return new Postgresql(
            [
                "host"     => PBX_HOST,
                'port'     => PBX_PORT,
                "username" => PBX_USER,
                "password" => PBX_PASSWORD,
                "dbname"   => PBX_DB
            ]
        );
    });

    
    /* CDR database */
    $di->set('cdr', function () {
        return new Postgresql([
            "host"     => CDR_HOST,
            'port'     => CDR_PORT,
            "username" => CDR_USER,
            "password" => CDR_PASSWORD,
            "dbname"   => CDR_DB
        ]);
    });

    /* order database */
    $di->set('order', function () {
        return new Postgresql([
            "host"     => ORDER_HOST,
            'port'     => ORDER_PORT,
            "username" => ORDER_USER,
            "password" => ORDER_PASSWORD,
            "dbname"   => ORDER_DB
        ]);
    });

    $di->set('radius', function() {
        $redis = new Redis();
        $redis->connect(RADIUS_HOST, RADIUS_PORT);
        $redis->select(RADIUS_DB);
        return $redis;
    });
    
    /* Setup the view component */
    $di->set('view', function () {
        $view = new View();
        $view->setViewsDir('../app/views/');
        return $view;
    });

    /* Setup php Excel*/
    $di->set('phpexcel', function () {
        $phpexcel = new PHPExcel();
	return $phpexcel;
    });

    $di->set('flash', function () {
        $flash = new FlashDirect(
            array(
                'error'   => 'form-group alert alert-danger',
                'success' => 'form-group alert alert-success',
                'notice'  => 'form-group alert alert-info',
                'warning' => 'form-group alert alert-warning'
            )
        );

        return $flash;
    });
    
    /* Setup a base URI so that all generated URIs include the "tutorial" folder */
    $di->set('url', function () {
        $url = new UrlProvider();
        $url->setBaseUri('/');
        return $url;
    });

    /* Handle the request */
    $application = new Application($di);

    echo $application->handle()->getContent();
} catch (\Exception $e) {
    echo "Error: ", $e->getMessage();
}
