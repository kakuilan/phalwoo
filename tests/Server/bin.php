<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/13
 * Time: 17:27
 * Desc: -
 */


$loader = require __DIR__ .'/../../vendor/autoload.php';
$conf = require_once 'config.php';
Tests\Server\MyServer::parseCommands();
Tests\Server\MyServer::instance()->setConf($conf)->run();