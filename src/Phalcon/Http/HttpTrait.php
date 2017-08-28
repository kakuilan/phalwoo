<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/26
 * Time: 14:48
 * Desc: -
 */


namespace Lkk\Phalwoo\Phalcon\Http;

use Phalcon\DiInterface;
use Phalcon\Http\Request\Exception as RequestException;
use Phalcon\Http\Response\Exception as ResponseException;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;

trait HttpTrait {

    /**
     * @var DiInterface
     */
    //protected $_dependencyInjector;

    /**
     * @var SwooleRequest
     */
    protected $_swooleRequest = null;

    /**
     * @var SwooleResponse
     */
    protected $_swooleResponse = null;

    /**
     * @param $di
     * @return DiInterface
     */
    private function _getDi($di) {
        if(!$di || !($di instanceof DiInterface)){
            return $this->_dependencyInjector;
        }

        return $di;
    }

    /**
     * @param null $di
     * @return mixed|SwooleRequest
     * @throws RequestException
     */
    protected function getSwooleRequest($di =null) {
        if($this->_swooleRequest) return $this->_swooleRequest;

        $di = $this->_getDi($di);

        $this->_swooleRequest = $di->get('swooleRequest');

        if (!$this->_swooleRequest) {
            throw new RequestException('swoole request is empty',2314131);
        }

        return $this->_swooleRequest;
    }

    /**
     * @param null $di
     * @return mixed|SwooleResponse
     * @throws ResponseException
     */
    protected function getSwooleResponse($di=null) {
        if($this->_swooleResponse) return $this->_swooleResponse;

        $di = $this->_getDi($di);
        $this->_swooleResponse = $di->get('swooleResponse');
        if (!$this->_swooleResponse) {
            throw new ResponseException('swoole response is empty',2314131);
        }

        return $this->_swooleResponse;
    }

}