<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/20
 * Time: 15:56
 * Desc: -
 */


namespace Lkk\Phalwoo\Phalcon;

use Phalcon\Di\InjectionAwareInterface;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\Request\Exception;
use Lkk\Phalwoo\Server\SwooleServer;
use Lkk\Phalwoo\Phalcon\Request\File;


class Request implements RequestInterface, InjectionAwareInterface {


    private $_dependencyInjector;

    private $_filter;

    /**
     * @var \swoole_http_request
     */
    private $request;


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
     * 设置swoole的request
     * @param \swoole_http_request $request
     */
    public function setSwooleRequest(\swoole_http_request $request) {
        $this->request = $request;
    }


    /**
     * @return \swoole_http_request
     */
    public function getSwooleRequest() {
        return $this->request;
    }


    /**
     * 获取参数数据
     * @param int  $sourceType 输入源类型
     * @param null $name 参数名
     * @param null $filters 过滤器
     * @param null $defaultValue 默认值
     * @param bool $notAllowEmpty 不允许为空
     * @param bool $noRecursive 是否递归过滤
     *
     * @return array|mixed|null
     * @throws Exception
     */
    protected function getParamData($sourceType, $name = null, $filters = null, $defaultValue = null, $notAllowEmpty = false, $noRecursive = false) {
        switch ($sourceType) {
            case INPUT_REQUEST:
            case INPUT_GET:
                $source = $this->request->get ?? [];
                break;
            case INPUT_POST:
                $source = $this->request->post ?? [];
                break;
            case INPUT_COOKIE:
                $source = $this->request->cookie ?? [];
                break;
            case INPUT_SERVER:
                $source = $this->request->server ?? [];
                break;
            default:
                $source = [];
        }

        if ($name === null) {
            return $source;
        }

        $value = isset($source[$name]) ? $source[$name] : null;

        if (!$value) {
            return $defaultValue;
        }


        if ($filters !== null) {
            if (!is_object($this->_filter) || empty($this->_filter)) {
                $msg = "A dependency injection object is required to access the 'filter' service";
                if (!is_object($this->_dependencyInjector)) {
                    throw new Exception($msg);
                }
                $this->_filter = $this->_dependencyInjector->getShared("filter");
                if(!is_object($this->_filter)) {
                    throw new Exception($msg);
                }
            }

            $value = $this->_filter->sanitize($value, $filters, $noRecursive);
        }

        if (empty($value) && $notAllowEmpty === true) {
            return $defaultValue;
        }

        return $value;
    }


    /**
     * Gets a variable from the $_REQUEST superglobal applying filters if needed
     *
     * @param string $name
     * @param string|array $filters
     * @param mixed $defaultValue
     * @return mixed
     */
    public function get($name = null, $filters = null, $defaultValue = null, $notAllowEmpty = false, $noRecursive = false) {
        return $this->getParamData(INPUT_REQUEST, $name, $filters, $defaultValue, $notAllowEmpty, $noRecursive);
    }


    /**
     * Gets a variable from the $_POST superglobal applying filters if needed
     *
     * @param string $name
     * @param string|array $filters
     * @param mixed $defaultValue
     * @return mixed
     */
    public function getPost($name = null, $filters = null, $defaultValue = null, $notAllowEmpty = false, $noRecursive = false) {
        return $this->getParamData(INPUT_POST, $name, $filters, $defaultValue, $notAllowEmpty, $noRecursive);
    }


    /**
     * Gets variable from $_GET superglobal applying filters if needed
     *
     * @param string $name
     * @param string|array $filters
     * @param mixed $defaultValue
     * @return mixed
     */
    public function getQuery($name = null, $filters = null, $defaultValue = null, $notAllowEmpty = false, $noRecursive = false) {
        return $this->getParamData(INPUT_GET, $name, $filters, $defaultValue, $notAllowEmpty, $noRecursive);
    }


    /**
     * Gets variable from $_COOKIE superglobal applying filters if needed
     *
     * @param string $name
     * @param string|array $filters
     * @param mixed $defaultValue
     * @return mixed
     */
    public function getCookie($name = null, $filters = null, $defaultValue = null, $notAllowEmpty = false, $noRecursive = false) {
        return $this->getParamData(INPUT_COOKIE, $name, $filters, $defaultValue, $notAllowEmpty, $noRecursive);
    }


    /**
     * Gets variable from $_SERVER superglobal
     *
     * @param string $name
     * @return mixed
     */
    public function getServer($name) {
        return $this->request->server[$name] ?? null;
    }


    /**
     * Checks whether $_REQUEST superglobal has certain index
     *
     * @param string $name
     * @return bool
     */
    public function has($name) {
        return isset($this->request->get[$name]) || isset($this->request->post[$name]);
    }


    /**
     * Checks whether $_POST superglobal has certain index
     *
     * @param string $name
     * @return bool
     */
    public function hasPost($name) {
        return isset($this->request->post[$name]);
    }


    /**
     * Checks whether the PUT data has certain index
     *
     * @param string $name
     * @return bool
     */
    public function hasPut($name) {
        return isset($this->request->put[$name]);
    }


    /**
     * Checks whether $_GET superglobal has certain index
     *
     * @param string $name
     * @return bool
     */
    public function hasQuery($name) {
        return isset($this->request->get[$name]);
    }


    /**
     * Checks whether $_COOKIE superglobal has certain index
     *
     * @param string $name
     * @return bool
     */
    public function hasCookie($name) {
        return isset($this->request->cookie[$name]);
    }


    /**
     * Checks whether $_SERVER superglobal has certain index
     *
     * @param string $name
     * @return bool
     */
    public function hasServer($name) {
        return isset($this->request->server[$name]);
    }


    /**
     * Gets HTTP header from request data
     *
     * @param string $header
     * @return string
     */
    public function getHeader($header) {
        return $this->request->header[$header];
    }


    /**
     * Gets HTTP schema (http/https)
     *
     * @return string
     */
    public function getScheme() {
        return stripos($this->request->server['server_protocol'], 'https') ? 'https' : 'http';
    }


    /**
     * Checks whether request has been made using ajax. Checks if $_SERVER["HTTP_X_REQUESTED_WITH"] === "XMLHttpRequest"
     *
     * @return bool
     */
    public function isAjax() {
        return (isset($this->request->header["x_requested_with"]) && $this->request->header["x_requested_with"] === "XMLHttpRequest")
            OR (isset($this->request->server["HTTP_X_REQUESTED_WITH"]) && $this->request->server["HTTP_X_REQUESTED_WITH"] === "XMLHttpRequest");
    }


    /**
     * Checks whether request has been made using SOAP
     *
     * @return bool
     */
    public function isSoapRequested() {
        //TODO
        return false;
    }


    /**
     * Checks whether request has been made using any secure layer
     *
     * @return bool
     */
    public function isSecureRequest() {
        //TODO
        return false;
    }


    /**
     * Gets HTTP raw request body
     *
     * @return string
     */
    public function getRawBody() {
        return $this->request->rawContent();
    }


    /**
     * Gets active server address IP
     *
     * @return string
     */
    public function getServerAddress() {
        return $this->request->server['server_addr'] ?? SwooleServer::getLocalIp();
    }


    /**
     * Gets active server name
     *
     * @return string
     */
    public function getServerName() {
        return $this->request->server['server_name'] ?? gethostname();
    }


    /**
     * Gets host name used by the request
     *
     * @return string
     */
    public function getHttpHost() {
        return $this->request->header['host'];
    }


    /**
     * Gets information about the port on which the request is made
     *
     * @return int
     */
    public function getPort() {
        return $this->request->server['server_port'];
    }


    /**
     * Gets most possibly client IPv4 Address. This methods searches in
     * $_SERVER["REMOTE_ADDR"] and optionally in $_SERVER["HTTP_X_FORWARDED_FOR"]
     *
     * @param bool $trustForwardedHeader
     * @return string
     */
    public function getClientAddress($trustForwardedHeader = false) {
        $address = null;

        /**
         * Proxies uses this IP
         */
        if ($trustForwardedHeader) {
            if (isset($this->request->header['x_forwarded_for'])) {
                $address = $this->request->header['x_forwarded_for'];
            } else if (isset($this->request->header['client_ip'])) {
                $address = $this->request->header['client_ip'];
            }
        }

        if ($address === null) {
            $address = $this->request->server['remote_addr'];
        }

        if ($address) {
            if (strpos($address, ',') !== false) {
                return explode(',', $address)[0];
            }

            return $address;
        }

        return false;
    }


    /**
     * Gets HTTP method which request has been made
     *
     * @return string
     */
    public function getMethod() {
        return $this->request->server['request_method'];
    }


    /**
     * Gets HTTP user agent used to made the request
     *
     * @return string
     */
    public function getUserAgent() {
        return $this->request->header['user-agent'];
    }


    /**
     * Check if HTTP method match any of the passed methods
     *
     * @param string|array $methods
     * @param bool $strict
     * @return bool
     */
    public function isMethod($methods, $strict = false) {
        $http_method = $this->getMethod();

        if (!is_array($methods)) {
            $methods = [$methods];
        }

        foreach ($methods as $method) {
            if ($http_method == $method) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks whether HTTP method is POST. if $_SERVER["REQUEST_METHOD"] === "POST"
     *
     * @return bool
     */
    public function isPost() {
        return $this->isMethod('POST');
    }


    /**
     * Checks whether HTTP method is GET. if $_SERVER["REQUEST_METHOD"] === "GET"
     *
     * @return bool
     */
    public function isGet() {
        return $this->isMethod('GET');
    }


    /**
     * Checks whether HTTP method is PUT. if $_SERVER["REQUEST_METHOD"] === "PUT"
     *
     * @return bool
     */
    public function isPut() {
        return $this->isMethod('PUT');
    }


    /**
     * Checks whether HTTP method is HEAD. if $_SERVER["REQUEST_METHOD"] === "HEAD"
     *
     * @return bool
     */
    public function isHead() {
        return $this->isMethod('HEAD');
    }


    /**
     * Checks whether HTTP method is DELETE. if $_SERVER["REQUEST_METHOD"] === "DELETE"
     *
     * @return bool
     */
    public function isDelete() {
        return $this->isMethod('DELETE');
    }


    /**
     * Checks whether HTTP method is OPTIONS. if $_SERVER["REQUEST_METHOD"] === "OPTIONS"
     *
     * @return bool
     */
    public function isOptions() {
        return $this->isMethod('OPTIONS');
    }


    /**
     * Checks whether HTTP method is PURGE (Squid and Varnish support). if $_SERVER["REQUEST_METHOD"] === "PURGE"
     *
     * @return bool
     */
    public function isPurge() {
        return $this->isMethod('PURGE');
    }


    /**
     * Checks whether HTTP method is TRACE. if $_SERVER["REQUEST_METHOD"] === "TRACE"
     *
     * @return bool
     */
    public function isTrace() {
        return $this->isMethod('TRACE');
    }


    /**
     * Checks whether HTTP method is CONNECT. if $_SERVER["REQUEST_METHOD"] === "CONNECT"
     *
     * @return bool
     */
    public function isConnect() {
        return $this->isMethod('CONNECT');
    }


    /**
     * Checks whether request include attached files
     *
     * @param boolean $onlySuccessful
     * @return boolean
     */
    public function hasFiles($onlySuccessful = false) {
        $res = false;
        if(isset($this->request->files)) {
            if($onlySuccessful) {
                foreach ($this->request->files as $file) {
                    if($file['error']==0) {
                        $res = true;
                        break;
                    }
                }
            }else{
                $res = true;
            }
        }

        return $res;
    }


    /**
     * Gets attached files as Phalcon\Http\Request\FileInterface compatible instances
     *
     * @param bool $onlySuccessful
     * @return \Phalcon\Http\Request\FileInterface[]
     */
    public function getUploadedFiles($onlySuccessful = false) {
        $res = [];
        if(isset($this->request->files)) {
            foreach ($this->request->files as $file) {
                $fileObj = new File();
                $fileObj->setFile($file);

                if($onlySuccessful) {
                    if($file['error']==0) array_push($res, $fileObj);
                }else{
                    array_push($res, $fileObj);
                }
            }
        }

        return $res;
    }


    /**
     * Gets web page that refers active request. ie: http://www.google.com
     *
     * @return string
     */
    public function getHTTPReferer() {
        return $this->request->header['referer'];
    }


    /**
     * Gets array with mime/types and their quality accepted by the browser/client from $_SERVER["HTTP_ACCEPT"]
     *
     * @return array
     */
    public function getAcceptableContent() {
        return explode(',', $this->request->header['accept']);
    }


    /**
     * Gets best mime/type accepted by the browser/client from $_SERVER["HTTP_ACCEPT"]
     *
     * @return string
     */
    public function getBestAccept() {
        $arr = explode(',', $this->request->header['accept']);
        return current($arr);
    }


    /**
     * Gets charsets array and their quality accepted by the browser/client from $_SERVER["HTTP_ACCEPT_CHARSET"]
     *
     * @return array
     */
    public function getClientCharsets() {
        //TODO
        return[];
    }


    /**
     * Gets best charset accepted by the browser/client from $_SERVER["HTTP_ACCEPT_CHARSET"]
     *
     * @return string
     */
    public function getBestCharset() {
        //TODO
        return '';
    }


    /**
     * Gets languages array and their quality accepted by the browser/client from _SERVER["HTTP_ACCEPT_LANGUAGE"]
     *
     * @return array
     */
    public function getLanguages() {
        return explode(',', $this->request->header['accept-language']);
    }


    /**
     * Gets best language accepted by the browser/client from $_SERVER["HTTP_ACCEPT_LANGUAGE"]
     *
     * @return string
     */
    public function getBestLanguage() {
        $arr = explode(',', $this->request->header['accept-language']);
        return current($arr);
    }


    /**
     * Gets auth info accepted by the browser/client from $_SERVER["PHP_AUTH_USER"]
     *
     * @return array
     */
    public function getBasicAuth() {
        //TODO
        return [];
    }


    /**
     * Gets auth info accepted by the browser/client from $_SERVER["PHP_AUTH_DIGEST"]
     *
     * @return array
     */
    public function getDigestAuth() {
        //TODO
        return [];
    }


    /**
     * 获取uri
     * @return mixed
     */
    public function getUri() {
        return $this->request->server['request_uri'];
    }



}