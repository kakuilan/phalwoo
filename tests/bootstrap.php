<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/12
 * Time: 21:32
 * Desc: -测试引导文件
 */


define('DS', str_replace('\\', '/', DIRECTORY_SEPARATOR));
define('PS', PATH_SEPARATOR);

$loader = require __DIR__ .'/../vendor/autoload.php';
$loader->addPsr4('Tests\\', __DIR__);
