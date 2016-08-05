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
use Phalcon\Acl\Adapter\Memory as AclList;
use Phalcon\Mvc\Dispatcher as MvcDispatcher;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Mvc\Dispatcher\Exception as DispatchException;
use Phalcon\Flash\Direct as FlashDirect;
use Phalcon\Db\Adapter\Pdo\Postgresql;

try {
    /* load config file */
    require '../config.php';
    require '../app/controllers/ControllerBase.php';

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

        $Super = new Role("Super");

        $acl->addRole($Super);

        $Company = new Resource('Company');
        $Task = new Resource('Task');
        $User = new Resource('User');
        $Agent = new Resource('Agent');
        $Sound = new Resource('Sound');
        $Service = new Resource('Service');
        $Gateway = new Resource('Gateway');
        $Status = new Resource('Status');
        $Logs = new Resource('Logs');
        $Error = new Resource('Error');

        $acl->addResource($Company, ['index', 'create', 'edit', 'update', 'delete', 'attack']);
        $acl->addResource($Task, ['index', 'getStatus']);
        $acl->addResource($User, ['index', 'create', 'edit', 'update', 'delete']);
        $acl->addResource($Agent, ['index', 'list', 'create', 'batch', 'delete', 'getAgent']);
        $acl->addResource($Sound, ['index', 'pass', 'reject', 'upload', 'edit', 'update', 'delete']);
        $acl->addResource($Service, ['index', 'start', 'stop']);
        $acl->addResource($Gateway, ['index', 'edit', 'update']);
        $acl->addResource($Status, ['index']);
        $acl->addResource($Logs, ['index']);
        $acl->addResource($Error, ['index', 'reject']);

        $acl->allow('Super', 'Company', ['index', 'create', 'edit', 'update', 'delete', 'attack']);
        $acl->allow('Super', 'Task', ['index', 'getStatus']);
        $acl->allow('Super', 'User', ['index', 'create', 'edit', 'update', 'delete']);
        $acl->allow('Super', 'Agent', ['index', 'list', 'create', 'batch', 'delete', 'getAgent']);
        $acl->allow('Super', 'Sound',  ['index', 'pass', 'reject', 'upload', 'edit', 'update', 'delete']);
        $acl->allow('Super', 'Service', ['index', 'start', 'stop']);
        $acl->allow('Super', 'Gateway', ['index', 'edit', 'update']);
        $acl->allow('Super', 'Status', ['index']);
        $acl->allow('Super', 'Logs', ['index']);
        $acl->allow('Super', 'Error', ['index', 'reject']);

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

    /* Handle the request */
    $application = new Application($di);

    echo $application->handle()->getContent();
} catch (\Exception $e) {
    echo "Error: ", $e->getMessage();
}
