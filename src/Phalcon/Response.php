<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/20
 * Time: 19:27
 * Desc: -
 */


namespace Lkk\Phalwoo\Phalcon;


use Phalcon\Http\Response as PhalResponse;
use Phalcon\Http\Response\Exception;

class Response extends PhalResponse {

    /**
     * @var \swoole_http_response
     */
    private $response;


    /**
     * @param \swoole_http_response $response
     */
    public function setSwooleResponse(\swoole_http_response $response) {
        $this->response = $response;
    }


    /**
     * @return \swoole_http_response
     */
    public function getSwooleResponse() {
        return $this->response;
    }


    public function sendCookies() {
        if ($this->_cookies) {
            $this->_cookies->send();
        }
    }


    public function send() {
        if ($this->_sent) {
            throw new Exception("Response was already sent");
        }

        if ($this->_headers) {
            $headers = $this->_headers->toArray();

            foreach ($headers as $k => $v) {
                $this->response->header($k, $v);
            }
        }

        if ($this->_cookies) {
            $this->_cookies->send();
        }

        return $this;
    }


    public function redirect($location = null, $externalRedirect = false, $statusCode = 302) {
        $this->response->status($statusCode);
        $this->response->header('location', $location);
        $this->response->end();
        return $this;
    }



}