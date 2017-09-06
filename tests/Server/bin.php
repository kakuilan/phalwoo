<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/13
 * Time: 17:27
 * Desc: -
 */


define('DS', str_replace('\\', '/', DIRECTORY_SEPARATOR));
define('PS', PATH_SEPARATOR);

$loader = require __DIR__ .'/../../vendor/autoload.php';
$loader->addPsr4('Tests\\', dirname(__DIR__));

$conf = require_once 'config.php';


Tests\Server\MyServer::parseCommands();
Tests\Server\MyServer::instance()->setConf($conf)->run();

/*$boot = new \Tests\Server\TestServer();
$boot->setConf($conf)->initServer()->startServer();*/
