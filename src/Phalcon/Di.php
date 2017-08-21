<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/20
 * Time: 15:41
 * Desc: -DI
 */


namespace Lkk\Phalwoo\Phalcon;

use Phalcon\Di\FactoryDefault;


class Di extends FactoryDefault {


    public function __construct(\swoole_http_request $swooleRequest, \swoole_http_response $swooleResponse) {
        parent::__construct();

        $request  = new Request();
        $response = new Response();

        $request->setSwooleRequest($swooleRequest);
        $response->setSwooleResponse($swooleResponse);

        $this->setShared('request', $request);
        $this->setShared('response', $response);


    }


}
