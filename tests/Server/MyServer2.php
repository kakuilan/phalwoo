<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/13
 * Time: 16:53
 * Desc: -MY服务器
 */


namespace Tests\Server;

use Lkk\Helpers\CommonHelper;
use Phalcon\Mvc\Micro;
use Lkk\Phalwoo\Server\SwooleServer;
use Lkk\Phalwoo\Phalcon\Di as PwDi;
use Lkk\Phalwoo\Phalcon\Http\Request as PwRequest;
use Lkk\Phalwoo\Phalcon\Http\Response as PwResponse;
use Lkk\Phalwoo\Phalcon\Http\Response\Cookies as PwCookies;
use Lkk\Phalwoo\Phalcon\Session\Adapter\Redis as PwSession;
use Lkk\Phalwoo\Server\DenyUserAgent;
use Phalcon\Crypt as PhCrypt;

use Lkk\Phalwoo\Server\Component\Log\SwooleLogger;
use Lkk\Phalwoo\Server\Component\Log\Handler\AsyncStreamHandler;

class MyServer2 extends SwooleServer {

    public $logger;

    public function __construct(array $vars = []) {
        parent::__construct($vars);

    }


    public function onStart($serv) {
        parent::onStart($serv);

        //logger test
        $logName = 'serlog';
        $logFile = __DIR__ .'/log/serlog.log';

        $this->logger = new SwooleLogger($logName, [], []);
        $this->logger->setDefaultHandler($logFile);

        //坑$this不是外面的MyServer
        //var_dump('onStart $this', $this);

    }


    public function onRequest($request, $response) {
        $response->header('X-Powered-By', ($this->conf['server_name'] ?? 'LkkServ'));
        $response->header('Server', ($this->conf['server_name'] ?? 'LkkServ'));
        //var_dump('swoole-request:------------', $request);

        //坑$this不是外面的MyServer
        $logg = SwooleServer::getProperty('logger');
        var_dump('onRequest $this', $this->logger, $logg, $this);

        /*$this->logger->info('request:', [
            'header' => $request->header ?? '',
            'server' => $request->server ?? '',
            'get' => $request->get ?? '',
            'post' => $request->post ?? '',
        ]);*/


        $sendRes = parent::onRequest($request, $response);
        if(is_bool($sendRes)) return $sendRes;

        $di = new PwDi();
        $app = new Micro($di);

        $di->setShared('boot', $this);
        $di->setShared('swooleRequest', $request);
        $di->setShared('swooleResponse', $response);

        //加密组件放在cookie和denAgent前面
        $crypt = new PhCrypt();
        $crypt->setKey('hello');
        $crypt->setPadding(PhCrypt::PADDING_ZERO);
        $di->setShared('crypt', $crypt);

        //TODO 检查客户端,防止爬虫和压力测试
        $denAgent = new DenyUserAgent();
        $denAgent->setRequest($request);
        $denAgent->setDI($di);
        $denAgent->setAllowBench(true);
        $agentUuid = $denAgent->getAgentUuid();
        $di->setShared('denAgent', $denAgent);

        $chkAgen = $denAgent->checkAll();
        //var_dump('$chkAgen', $chkAgen, $denAgent->error);
        if(!$chkAgen) {
            return $response->end();
        }

        $di->setShared('request', function () use ($di) {
            $request = new PwRequest();
            $request->setDi($di);
            return $request;
        });

        $di->setShared('response', function () use ($di) {
            $response = new PwResponse();
            $response->setDi($di);
            return $response;
        });

        $di->setShared('cookies', function () use ($di) {
            $cookies = new PwCookies();
            $cookies->useEncryption(false);
            $cookies->setDI($di);
            return $cookies;
        });

        /*try {
            $resStr = date('Y-m-d H:i:s') . ' Hello World.';
        } catch (\Exception $e) {
            $resStr = $e->getMessage();
        }
        $response->end($resStr);*/

        $cookConf = [
            'domain'    => '192.168.128.130',   //Cookie 作用域
            'path'      => '/',         //Cookie 作用路径
            'lifetime'  => 0,           //Cookie 生命周期, 0为随浏览器进程
            'pre'       => 'ks_',       //Cookie 前缀
        ];
        $sessConf = [
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
            'cookie'            => $cookConf,
        ];

        //注意下面这几个方法顺序不能改
        $session = new PwSession($sessConf);
        $session->setDI($di);
        $di->setShared('session', $session);
        $session->start();

        $di->setShared('app', $app);
        $app->setDI($di);

        $app->get(
            "*",
            function () use($app) {
                $onlineNum = $this->di->getShared('session')->getSiteOnlineNum();
                $msg = 'Weclcom Phalcon Swoole! [*]' .date('Y-m-d H:i:s').' onlineNum:'.$onlineNum;
                return $msg;
            }
        );
        $app->get(
            "/",
            function () use($app, $session) {
                $onlineNum = $session->getSiteOnlineNum();
                //var_dump($app->getDI()->getShared('session'), $app->session, $this->session);
                //TODO 这里获取session错误,获取了默认的file,待解决
                $msg = 'Weclcom Phalcon Swoole! [/]' .date('Y-m-d H:i:s').' onlineNum:'. $onlineNum;
                return $msg;
            }
        );
        $app->get(
            "/index",
            function () use($app) {
                $onlineNum = $this->session->getSiteOnlineNum();
                $msg = '[index]! ' .date('Y-m-d H:i:s').' onlineNum:'.$onlineNum;
                return $msg;
            }
        );
        $app->notFound(function() use($app) {
            $onlineNum = $this->session->getSiteOnlineNum();
            $msg = 'Weclcom Phalcon Swoole! not found!' .date('Y-m-d H:i:s').' onlineNum:'.$onlineNum;
            return $msg;
        });

        $ret = $app->handle($request->server['request_uri']);
        if ($ret instanceof PwResponse) {
            $ret->send();
            $response->end($ret->getContent());
        } else if (is_string($ret)) {
            $response->end($ret);
        } else {
            $response->end();
        }

        unset($di, $app);

        $this->afterResponse($request, $response);
    }





}