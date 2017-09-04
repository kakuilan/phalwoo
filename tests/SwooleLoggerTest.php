<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/9/3
 * Time: 19:08
 * Desc: -服务端日志类测试
 */


namespace Tests;

use PHPUnit\Framework\TestCase;
use Lkk\Phalwoo\Server\Component\Log\SwooleLogger;
use Lkk\Phalwoo\Server\Component\Log\Handler\AsyncStreamHandler;

class SwooleLoggerTest extends TestCase {


    /**
     * 测试新建日志对象
     */
    public function testNewLogger() {
        $logName = 'test';
        $logFile = __DIR__ .'/log/test.log';

        $logger = new SwooleLogger($logName, [], []);
        $logger->setDefaultHandler($logFile);

        $logDir = dirname($logFile);
        $chk = file_exists($logDir);
        $this->assertTrue($chk);

        return $logger;
    }



    /**
     * @depends testNewLogger
     * 测试日志文件写入
     */
    public function testWrite($logger) {
        $num = 100000;

        $handler = $logger->getCurrentHandler();
        $handler->setMaxFileSize(20480);
        $handler->setMaxRecords(1000);

        $faker = \Faker\Factory::create();
        echo "start time:" .date('Y-m-d H:i:s') ." \r\n";
        for ($i=0;$i<=$num;$i++) {
            $logger->info('swooleLogger test', [
                'name' => $faker->name,
                'addr' => $faker->address,
                'tel' => $faker->phoneNumber,
                'ip' => $faker->ipv4,
                'time' => $faker->unixTime,
            ]);
        }



    }


}