<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/26
 * Time: 14:42
 * Desc: -重写Phalcon的Response类
 */


namespace Lkk\Phalwoo\Phalcon\Http;

use Phalcon\DiInterface;
use Phalcon\Di\InjectionAwareInterface;
use Phalcon\Http\ResponseInterface;
use Phalcon\Http\Response as PhalconResponse;
use Phalcon\Http\Response\HeadersInterface;
use Phalcon\Http\Response\CookiesInterface;
use Phalcon\Http\Response\Exception;
use Phalcon\Events\Manager;
use Lkk\Phalwoo\Phalcon\Http\Response\Headers;
use Lkk\Phalwoo\Phalcon\Http\HttpTrait;
use Lkk\Phalwoo\Phalcon\Http\Response\Cookies;
use Lkk\Helpers\ValidateHelper;
use Lkk\Helpers\FileHelper;

class Response extends PhalconResponse implements ResponseInterface , InjectionAwareInterface {

    use HttpTrait;

    /**
     * Sent
     *
     * @var boolean
     * @access protected
     */
    protected $_sent = false;

    /**
     * Content
     *
     * @var null|string
     * @access protected
     */
    protected $_content;

    /**
     * Headers
     *
     * @var null|HeadersInterface
     * @access protected
     */
    protected $_headers;

    /**
     * Cookies
     *
     * @var null|CookiesInterface
     * @access protected
     */
    protected $_cookies;

    /**
     * File
     *
     * @var null|string
     * @access protected
     */
    protected $_file;
    protected $_basePath;

    /**
     * Dependency Injector
     *
     * @var null|DiInterface
     * @access protected
     */
    protected $_dependencyInjector;

    /**
     * StatusCodes
     *
     * @var mixed
     * @access protected
     */
    protected $_statusCodes;


    /**
     * 返回结果是否json
     * @var bool
     */
    protected $_isJson = false;

    /**
     * Phalcon\Http\Response constructor
     *
     * @param mixed $content
     * @param mixed $code
     * @param mixed $status
     */
    public function __construct($content = null, $code = null, $status = null) {
        //parent::__construct($content, $code, $status);

        $this->_headers = new Headers();

        if($content !== null){
            $this->_content = $content;
        }

        if ($code !== null) {
            $this->setStatusCode($code, $status);
        }

    }


    /**
     * Sets the HTTP response code
     *
     * <code>
     * $response->setStatusCode(404, "Not Found");
     * </code>
     *
     * @param int $code
     * @param string $message
     * @return Response
     */
    public function setStatusCode($code, $message = null) {
        if (!ValidateHelper::isInteger($code)) {
            throw new Exception('Invalid parameter type.');
        }

        $headers = $this->getHeaders();
        $currentHeadersRaw = $headers->toArray();

        /**
         * We use HTTP/1.1 instead of HTTP/1.0
         *
         * Before that we would like to unset any existing HTTP/x.y headers
         */
        if (is_array($currentHeadersRaw)) {
            foreach ($currentHeadersRaw as $key => $value) {
                if (is_string($key) && strstr($key, 'HTTP/')) {
                    $headers->remove($key);
                }
            }
        }

        // if an empty message is given we try and grab the default for this
        // status code. If a default doesn't exist, stop here.
        if ($message === null) {
            if (!is_array($this->_statusCodes)) {
                $this->_statusCodes = [
                    // INFORMATIONAL CODES
                    100 => "Continue",
                    101 => "Switching Protocols",
                    102 => "Processing",
                    // SUCCESS CODES
                    200 => "OK",
                    201 => "Created",
                    202 => "Accepted",
                    203 => "Non-Authoritative Information",
                    204 => "No Content",
                    205 => "Reset Content",
                    206 => "Partial Content",
                    207 => "Multi-status",
                    208 => "Already Reported",
                    // REDIRECTION CODES
                    300 => "Multiple Choices",
                    301 => "Moved Permanently",
                    302 => "Found",
                    303 => "See Other",
                    304 => "Not Modified",
                    305 => "Use Proxy",
                    306 => "Switch Proxy", // Deprecated
                    307 => "Temporary Redirect",
                    // CLIENT ERROR
                    400 => "Bad Request",
                    401 => "Unauthorized",
                    402 => "Payment Required",
                    403 => "Forbidden",
                    404 => "Not Found",
                    405 => "Method Not Allowed",
                    406 => "Not Acceptable",
                    407 => "Proxy Authentication Required",
                    408 => "Request Time-out",
                    409 => "Conflict",
                    410 => "Gone",
                    411 => "Length Required",
                    412 => "Precondition Failed",
                    413 => "Request Entity Too Large",
                    414 => "Request-URI Too Large",
                    415 => "Unsupported Media Type",
                    416 => "Requested range not satisfiable",
                    417 => "Expectation Failed",
                    418 => "I'm a teapot",
                    422 => "Unprocessable Entity",
                    423 => "Locked",
                    424 => "Failed Dependency",
                    425 => "Unordered Collection",
                    426 => "Upgrade Required",
                    428 => "Precondition Required",
                    429 => "Too Many Requests",
                    431 => "Request Header Fields Too Large",
                    // SERVER ERROR
                    500 => "Internal Server Error",
                    501 => "Not Implemented",
                    502 => "Bad Gateway",
                    503 => "Service Unavailable",
                    504 => "Gateway Time-out",
                    505 => "HTTP Version not supported",
                    506 => "Variant Also Negotiates",
                    507 => "Insufficient Storage",
                    508 => "Loop Detected",
                    511 => "Network Authentication Required"
                ];
            }


            if (!isset($this->_statusCodes[$code])) {
                throw new Exception("Non-standard statuscode given without a message");
            }

            $defaultMessage = $this->_statusCodes[$code];
            $message = $defaultMessage;
        }

        $headers->setRaw('HTTP/1.1 ' . $code . ' ' . $message);
        /**
         * We also define a 'Status' header with the HTTP status
         */
        $headers->set('Status', $code . ' ' . $message);

        //swoole set status code
        $swooleResponse = $this->getSwooleResponse();
        $swooleResponse->status($code);

        return $this;
    }



    /**
     * Sets a headers bag for the response externally
     *
     * @param \Phalcon\Http\Response\HeadersInterface $headers
     * @return Response
     */
    public function setHeaders(\Phalcon\Http\Response\HeadersInterface $headers) {
        if (!is_object($headers) || $headers instanceof Headers === false) {
            throw new Exception('Invalid parameter type.');
        }

        $this->_headers = $headers;

        return $this;
    }


    /**
     * Sets a cookies bag for the response externally
     *
     * @param \Phalcon\Http\Response\CookiesInterface $cookies
     * @return Response
     */
    public function setCookies(\Phalcon\Http\Response\CookiesInterface $cookies) {
        if (!is_object($cookies) || $cookies instanceof Cookies === false) {
            throw new Exception('The cookies bag is not valid');
        }

        $this->_cookies = $cookies;

        return $this;
    }



    /**
     * Redirect by HTTP to another action or URL
     *
     * <code>
     * // Using a string redirect (internal/external)
     * $response->redirect("posts/index");
     * $response->redirect("http://en.wikipedia.org", true);
     * $response->redirect("http://www.example.com/new-location", true, 301);
     *
     * // Making a redirection based on a named route
     * $response->redirect(
     *     [
     *         "for"        => "index-lang",
     *         "lang"       => "jp",
     *         "controller" => "index",
     *     ]
     * );
     * </code>
     *
     * @param mixed $location
     * @param bool $externalRedirect
     * @param int $statusCode
     * @return Response
     */
    public function redirect($location = null, $externalRedirect = false, $statusCode = 302) {
        if ($externalRedirect) {
            $header = $location;
        } else {
            if (is_string($location) && strstr($location, '://')) {
                $matched = preg_match('/^[^:\\/?#]++:/', $location);
                if ($matched) {
                    $header = $location;
                } else {
                    $header = null;
                }
            } else {
                $header = null;
            }
        }

        if (!$header) {
            $url = $this->getDI()->getShared('url');
            $header = $url->get($location);
        }

        $swooleResponse = $this->getSwooleResponse();
        $swooleResponse->status($statusCode);
        $swooleResponse->header('Location', $header);
        $swooleResponse->end();

        return $this;
    }



    /**
     * Sends headers to the client
     *
     * @return Response
     */
    public function sendHeaders() {
        $headers = $this->_headers;
        if (is_object($headers)) {
            $headers->setDI($this->getDI());
            $headers->send();
        }

        return $this;
    }

    /**
     * Sends cookies to the client
     *
     * @return Response
     */
    public function sendCookies() {
        $cookies = $this->_cookies;
        if (is_object($cookies)) {
            $cookies->send();
        }

        /** @var Manager $eventManager */
        $eventManager = $this->_dependencyInjector->get('eventsManager');
        $eventManager->fire('response:beforeSendCookies', $this);

        return $this;
    }


    /**
     * 发送文件
     */
    public function sendFile() {
        if($this->_file) {
            $swooleResponse = $this->getSwooleResponse();
            $mime = FileHelper::getFileMime($this->_file, true);
            $swooleResponse->header('Content-Description', 'File Transfer');
            $swooleResponse->header('Content-Type', $mime);
            $swooleResponse->header('Content-Disposition', 'attachment; filename=' . $this->_basePath);
            $swooleResponse->header('Content-Transfer-Encoding', 'binary');
            $swooleResponse->sendfile($this->_file);
        }
    }


    /**
     * Prints out HTTP response to the client
     *
     * @return Response
     */
    public function send() {
        $this->sendHeaders();
        $this->sendCookies();

        //不发送具体内容,留给swoole response发送
        //TODO 判断 $this->_content 和 $this->_file

        $this->_sent = true;

        return $this;
    }


    /**
     * Sets an attached file to be sent at the end of the request
     *
     * @param string $filePath
     * @param mixed $attachmentName
     * @param mixed $attachment
     * @return Response
     */
    public function setFileToSend($filePath, $attachmentName = null, $attachment = true) {
        /* Type check */
        if (!is_string($filePath)) {
            throw new Exception('Invalid parameter type.');
        }

        if (!is_bool($attachment)) {
            throw new Exception('Invalid parameter type.');
        }

        if (!is_string($attachmentName)) {
            $basePath = basename($filePath);
        } else {
            $basePath = $attachmentName;
        }

        if ($attachment && file_exists($filePath)) {
            $this->_file = $filePath;
            $this->_basePath = $basePath;
        }

        return $this;
    }


    /**
     * 是否有要发送的文件
     * @return bool
     */
    public function hasFile() {
        return !empty($this->_file);
    }


    /**
     * 设置返回结果是否json
     * @param bool $status
     */
    public function setJsonStatus($status=false) {
        $this->_isJson = boolval($status);
    }


    /**
     * 检查返回是否json数据
     * @return bool
     */
    public function isJson() {
        return $this->_isJson;
    }


}
