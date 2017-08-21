<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/13
 * Time: 16:53
 * Desc: -MY服务器
 */


namespace Tests\Server;

use Lkk\Phalwoo\Server\SwooleServer;

class MyServer extends SwooleServer {


    public function __construct(array $vars = []) {
        parent::__construct($vars);
    }


    public function onRequest($request, $response) {
        $sendRes = parent::onRequest($request, $response);
        if(is_bool($sendRes)) return $sendRes;

        try {
            $resStr = date('Y-m-d H:i:s') . ' Hello World.';
        } catch (\Exception $e) {
            $resStr = $e->getMessage();
        }
        $response->end($resStr);

        $this->afterResponse($request, $response);
    }





}