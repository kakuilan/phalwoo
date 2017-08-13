<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/13
 * Time: 16:59
 * Desc: -服务器测试
 */


namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\Server\MyServer;
use Lkk\Helpers\ValidateHelper;

class ServerTest extends TestCase {


    /**
     * 获取服务器配置
     * @return mixed
     */
    public static function getServerConf() {
        $conf = require_once 'Server/config.php';
        return $conf;
    }


    /**
     * 测试-创建守护进程的服务器
     */
    public function testBuildDaemonServer() {
        $command = 'php Server/bin.php start -d';
        exec($command, $response);

        $conf = self::getServerConf();
        $binded  = ValidateHelper::isPortBinded('127.0.0.1', $conf['http_server']['port']);
        $this->assertTrue($binded);
    }


    /**
     * 测试-停止服务器
     */
    public function testStopServer() {
        $command = 'php Server/bin.php stop';
        exec($command, $response);

        $conf = self::getServerConf();
        $binded  = ValidateHelper::isPortBinded('127.0.0.1', $conf['http_server']['port']);
        $this->assertTrue(!$binded);
    }



}