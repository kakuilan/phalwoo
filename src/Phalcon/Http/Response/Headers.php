<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/26
 * Time: 14:42
 * Desc: -重写Phalcon的Response\Headers类
 */


namespace Lkk\Phalwoo\Phalcon\Http\Response;

use Phalcon\DiInterface;
use Phalcon\Di\InjectionAwareInterface;
use Phalcon\Http\Response\Headers as PhalconHeaders;
use Phalcon\Http\Response\HeadersInterface;
use Lkk\Phalwoo\Phalcon\Http\HttpTrait;

class Headers extends PhalconHeaders implements HeadersInterface,InjectionAwareInterface {

    use HttpTrait;
    protected $_dependencyInjector;

    /**
     * Sets the dependency injector
     *
     * @param \Phalcon\DiInterface $dependencyInjector
     */
    public function setDI(\Phalcon\DiInterface $dependencyInjector) {
        $this->_dependencyInjector = $dependencyInjector;
    }


    /**
     * Returns the internal dependency injector
     *
     * @return \Phalcon\DiInterface
     */
    public function getDI() {
        return $this->_dependencyInjector;
    }


    /**
     * Sends the headers to the client
     *
     * @return bool
     */
    public function send() {
        if(empty($this->_headers)) return false;

        $response = $this->getSwooleResponse();
        foreach($this->_headers as $header=>$value){
            $response->header($header, $value);
        }

        return true;
    }

}
