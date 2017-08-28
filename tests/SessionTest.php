<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/24
 * Time: 23:02
 * Desc: -
 */


namespace Tests;

use PHPUnit\Framework\TestCase;
use Lkk\Phalwoo\Phalcon\Session\Adapter\Redis as RedisSession;

class SessionTest extends TestCase {

    public function testRedisSession() {
        $conf = [
            'host'              => 'localhost',
            'port'              => 6379,
            'auth'              => null,
            'lifetime'          => '900', //秒,redis SESSION有效期
            'cookie_lifetime'   => 0, //秒,cookie PHPSESSID有效期,0为随浏览器
            'cookie_secure'     => true,
            'uniqueId'          => '_lkksys_', //隔离不同应用的会话数据
            'prefix'            => 'SESSION:',
            'name'              => null,
            'index'             => 1, //redis库号
            'cookie'            =>[
                'domain'    => '*',   //Cookie 作用域
                'path'      => '/',         //Cookie 作用路径
                'lifetime'  => 0,           //Cookie 生命周期, 0为随浏览器进程
                'pre'       => 'ks_',       //Cookie 前缀
            ],
        ];

        $session = new RedisSession($conf);
        $session->start();
        $redis = $session->getRedis();
        $liftime = $session->getLifetime();
        var_dump(111111111, $redis, $liftime);


    }

}