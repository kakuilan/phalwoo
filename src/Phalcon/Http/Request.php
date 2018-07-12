<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/26
 * Time: 11:12
 * Desc: -重写Phalcon的Request类
 */


namespace Lkk\Phalwoo\Phalcon\Http;

use Phalcon\Di\InjectionAwareInterface;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\Request\Exception;
use Lkk\Phalwoo\Server\SwooleServer;
use Lkk\Phalwoo\Phalcon\Request\File;

class Request implements RequestInterface, InjectionAwareInterface {

    use HttpTrait;

    protected $_dependencyInjector;


    protected $_rawBody;


    protected $_filter;


    protected $_putCache;


    protected $_httpMethodParameterOverride = false;


    protected $_strictHostCheck = false;


    private $_files;


    private $_requestUuid = null;


    private $_useMillisecond = 0; //内部处理时间,毫秒


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
        $this->getSwooleRequest();
        static $request;
        switch ($sourceType) {
            case INPUT_REQUEST:
                if(isset($this->_swooleRequest->get) && isset($this->_swooleRequest->post) && !empty($this->_swooleRequest->post)) {
                    if(is_null($request)) $request = array_merge($this->_swooleRequest->get, $this->_swooleRequest->post);
                    $source = $request;
                }else{
                    $source = $this->_swooleRequest->get ?? ($this->_swooleRequest->post ?? []);
                }
                break;
            case INPUT_GET:
                $source = $this->_swooleRequest->get ?? [];
                break;
            case INPUT_POST:
                $source = $this->_swooleRequest->post ?? [];
                break;
            case INPUT_COOKIE:
                $source = $this->_swooleRequest->cookie ?? [];
                break;
            case INPUT_SERVER:
                $source = $this->_swooleRequest->server ?? [];
                break;
            default:
                $source = [];
        }

        if ($name === null || $name==='') {
            return $source;
        }

        $value = isset($source[$name]) ? $source[$name] : null;

        if (is_null($value)) {
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
    public function getServer($name=null) {
        return (is_null($name) || $name==='') ? $this->_swooleRequest->server : ($this->_swooleRequest->server[$name] ?? null);
    }


    /**
     * Checks whether $_REQUEST superglobal has certain index
     *
     * @param string $name
     * @return bool
     */
    public function has($name) {
        return isset($this->_swooleRequest->get[$name]) || isset($this->_swooleRequest->post[$name]);
    }


    /**
     * Checks whether $_POST superglobal has certain index
     *
     * @param string $name
     * @return bool
     */
    public function hasPost($name) {
        return isset($this->_swooleRequest->post[$name]);
    }


    /**
     * Checks whether the PUT data has certain index
     *
     * @param string $name
     * @return bool
     */
    public function hasPut($name) {
        return isset($this->_swooleRequest->put[$name]);
    }


    /**
     * Checks whether $_GET superglobal has certain index
     *
     * @param string $name
     * @return bool
     */
    public function hasQuery($name) {
        return isset($this->_swooleRequest->get[$name]);
    }


    /**
     * Checks whether $_COOKIE superglobal has certain index
     *
     * @param string $name
     * @return bool
     */
    public function hasCookie($name) {
        return isset($this->_swooleRequest->cookie[$name]);
    }


    /**
     * Checks whether $_SERVER superglobal has certain index
     *
     * @param string $name
     * @return bool
     */
    public function hasServer($name) {
        return isset($this->_swooleRequest->server[$name]);
    }


    /**
     * Gets HTTP header from request data
     *
     * @param string $header
     * @return string
     */
    public function getHeader($header) {
        $header = strtolower($header);
        $header = str_replace(['http_','_'], ['','-'], $header);

        return $this->_swooleRequest->header[$header] ?? '';
    }


    /**
     * Gets HTTP schema (http/https)
     *
     * @return string
     */
    public function getScheme() {
        $proto = $this->_swooleRequest->header['x-forwarded-proto'] ?? ($this->_swooleRequest->server['server_protocol'] ?? '');
        return stripos($proto, 'https')!==false ? 'https' : 'http';
    }


    /**
     * Checks whether request has been made using ajax. Checks if $_SERVER["HTTP_X_REQUESTED_WITH"] === "XMLHttpRequest"
     *
     * @return bool
     */
    public function isAjax() {
        return (isset($this->_swooleRequest->header["x-requested-with"]) && $this->_swooleRequest->header["x-requested-with"] === "XMLHttpRequest")
            OR (isset($this->_swooleRequest->server["http-x-requested_with"]) && $this->_swooleRequest->server["http-x-requested_with"] === "XMLHttpRequest");
    }


    /**
     * Checks whether request has been made using SOAP
     *
     * @return bool
     */
    public function isSoapRequested() {
        if($this->getHeader('soapaction')){
            return true;
        }else{
            $contentType = $this->getContentType();
            if (!empty($contentType) && strpos($contentType,'application/soap+xml')!==false ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Checks whether request has been made using any secure layer
     *
     * @return bool
     */
    public function isSecureRequest() {
        return $this->getScheme() === 'https';
    }


    /**
     * Gets HTTP raw request body
     *
     * @return string
     */
    public function getRawBody() {
        if (empty($this->_rawBody)) {
            $this->_rawBody = $this->getSwooleRequest()->rawContent();
        }

        return $this->_rawBody;
    }


    /**
     * Gets decoded JSON HTTP raw request body
     *
     * @param bool $associative
     * @return array|bool|\stdClass
     */
    public function getJsonRawBody($associative = false) {
        $rawBody = $this->getRawBody();
        if(!is_string($rawBody)){
            return false;
        }

        return json_decode($rawBody,$associative);
    }


    /**
     * Gets active server address IP
     *
     * @return string
     */
    public function getServerAddress() {
        return $this->_swooleRequest->server['server_addr'] ?? SwooleServer::getLocalIp();
    }


    /**
     * Gets active server name
     *
     * @return string
     */
    public function getServerName() {
        return $this->_swooleRequest->server['server_name'] ?? gethostname();
    }


    /**
     * Gets host name used by the request.
     * `Request::getHttpHost` trying to find host name in following order:
     * - `$_SERVER['HTTP_HOST']`
     * - `$_SERVER['SERVER_NAME']`
     * - `$_SERVER['SERVER_ADDR']`
     * Optionally `Request::getHttpHost` validates and clean host name.
     * The `Request::$_strictHostCheck` can be used to validate host name.
     * Note: validation and cleaning have a negative performance impact because they use regular expressions.
     * <code>
     * use Phalcon\Http\Request;
     * $request = new Request;
     * $_SERVER['HTTP_HOST'] = 'example.com';
     * $request->getHttpHost(); // example.com
     * $_SERVER['HTTP_HOST'] = 'example.com:8080';
     * $request->getHttpHost(); // example.com:8080
     * $request->setStrictHostCheck(true);
     * $_SERVER['HTTP_HOST'] = 'ex=am~ple.com';
     * $request->getHttpHost(); // UnexpectedValueException
     * $_SERVER['HTTP_HOST'] = 'ExAmPlE.com';
     * $request->getHttpHost(); // example.com
     * </code>
     *
     * @return string
     */
    public function getHttpHost() {
        $strict = $this->_strictHostCheck;
        $host = $this->getHeader('host');
        if(!$host){
            $host = $this->getServer('server_name');
            if(!$host){
                $host = $this->getServer('server_addr');
            }
        }

        if ($host && $strict) {
            $host = strtolower(trim($host));
            if(strpos($host,':') !==false){
                //$host = preg_replace('/:[[:digit:]]+$/', '', $host);
                $host = preg_replace('/:[\d]+$/','', $host);
            }

            if('' != preg_replace('/[a-z0-9]+\.?/','',$host)){
                throw new \UnexpectedValueException('Invalid host ',$host);
            }
        }

        return (string)$host;
    }

    /**
     * Sets if the `Request::getHttpHost` method must be use strict validation of host name or not
     *
     * @param bool $flag
     * @return Request
     */
    public function setStrictHostCheck($flag = true) {
        $this->_strictHostCheck = $flag;
        return $this;
    }

    /**
     * Checks if the `Request::getHttpHost` method will be use strict validation of host name or not
     *
     * @return bool
     */
    public function isStrictHostCheck() {
        return $this->_strictHostCheck;
    }



    /**
     * Gets information about the port on which the request is made.
     *
     * @return int
     */
    public function getPort() {
        $host = $this->getHeader('host');
        if($host){
            $pos = strpos($host, ':');
            if(false !== $pos){
                return intval(substr($host,$pos+1));
            }else{
                return 'https' === $this->getScheme() ? 443 : 80;
            }
        }

        return intval($this->getServer('server_port'));
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
            if (isset($this->_swooleRequest->header['x-forwarded-for'])) {
                $address = $this->_swooleRequest->header['x-forwarded-for'];
            } else if (isset($this->_swooleRequest->header['client-ip'])) {
                $address = $this->_swooleRequest->header['client-ip'];
            }
        }

        if ($address === null) {
            if(isset($this->_swooleRequest->header['x-real-ip'])) {
                $address = $this->_swooleRequest->header['x-real-ip'];
            }else{
                $address = $this->_swooleRequest->server['remote_addr'];
            }
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
     * Checks if a method is a valid HTTP method
     *
     * @param string $method
     * @return bool
     */
    public static function isValidHttpMethod($method) {
        if(in_array(strtoupper($method), ['GET','POST','PUT','DELETE','HEAD','OPTIONS','PATCH','PURGE','GRACE','CONNECT'])) {
            return true;
        }

        return false;
    }


    /**
     * Gets HTTP method which request has been made
     * If the X-HTTP-Method-Override header is set, and if the method is a POST,
     * then it is used to determine the "real" intended HTTP method.
     * The _method request parameter can also be used to determine the HTTP method,
     * but only if setHttpMethodParameterOverride(true) has been called.
     * The method is always an uppercased string.
     *
     * @return string
     */
    public final function getMethod() {
        $returnMethod = '';

        if ($requestMethod = $this->getServer('request_method')) {
            $returnMethod = $requestMethod;
        }

        if('POST' == $requestMethod){
            if($overridedMethod = $this->getHeader('x-http-method-override')){
                $returnMethod = $overridedMethod;
            }else if($this->_httpMethodParameterOverride){
                if($spoofedMethod = $this->get('_method')){
                    $returnMethod = $spoofedMethod;
                }
            }
        }

        if(!self::isValidHttpMethod($returnMethod)){
            $returnMethod = 'GET';
        }

        return strtoupper($returnMethod);
    }


    /**
     * Gets HTTP user agent used to made the request
     *
     * @return string
     */
    public function getUserAgent() {
        return $this->_swooleRequest->header['user-agent'] ?? '';
    }


    /**
     * Check if HTTP method match any of the passed methods
     * When strict is true it checks if validated methods are real HTTP methods
     *
     * @param string|array $methods
     * @param bool $strict
     * @return bool
     * @throws Exception
     */
    public function isMethod($methods, $strict = false) {
        $httpMethod = $this->getMethod();
        if (is_string($methods)) {
            if ($strict && !self::isValidHttpMethod($methods)) {
                throw new Exception('Invalid HTTP method:' . $methods);
            }

            return $methods == $httpMethod;
        }

        if(is_array($methods)){
            foreach ($methods as $method) {
                if($this->isMethod($method, $strict)){
                    return true;
                }
            }
            return false;
        }

        if($strict){
            throw new Exception('Invalid HTTP method:non-string');
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
        if(isset($this->_swooleRequest->files)) {
            if($onlySuccessful) {
                foreach ($this->_swooleRequest->files as $file) {
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
        if(is_null($this->_files)) {
            $this->_files = [];
            if(isset($this->_swooleRequest->files)) {
                foreach ($this->_swooleRequest->files as $k=>$file) {
                    $fileObj = new File($file, $k);
                    array_push($this->_files, $fileObj);
                }
            }
        }

        if(!$onlySuccessful) return $this->_files;
        $res = [];
        foreach ($this->_files as $file) {
            if($file->getErrorCode()=='0') array_push($res, $file);
        }

        return $res;
    }


    /**
     * Returns the available headers in the request
     *
     * @return array
     */
    public function getHeaders() {
        $requestHeaders = $this->getSwooleRequest()->header;
        $headers = [];
        foreach($requestHeaders as $key=>$val){
            // abc-def -> Abc Def
            $name = ucwords(strtolower(str_replace('-', ' ', $key)));
            $name = str_replace(' ', '-', $name);
            $headers[$name] = $val;
        }

        return $headers;
    }



    /**
     * Gets web page that refers active request. ie: http://www.google.com
     *
     * @return string
     */
    public function getHTTPReferer() {
        return $this->_swooleRequest->header['referer'] ?? ($this->_swooleRequest->server['HTTP_REFERER'] ?? '');
    }


    /**
     * Process a request header and return an array of values with their qualities
     *
     * @param string $serverIndex
     * @param string $name
     * @return array
     */
    protected final function _getQualityHeader($serverIndex, $name) {
        $returnedParts = [];
        $tmpServer = $this->getHeader($serverIndex);
        $tmpServer = preg_split('/,\s*/',$tmpServer,-1,PREG_SPLIT_NO_EMPTY);

        foreach($tmpServer as $part){
            $headerParts = [];
            $tmpParts = preg_split('/\s*;\s*/', trim($part), -1, PREG_SPLIT_NO_EMPTY);
            foreach($tmpParts as $headerPart){
                if(strpos($headerPart,'=') !== false){
                    $split = explode('=', $headerPart, 2);

                    if ($split[0] == 'q') {
                        $headerParts['quality'] = (double)$split[1];
                    }else{
                        $headerParts[$split[0]] = $split[1];
                    }
                }else{
                    $headerParts[$name] = $headerPart;
                    $headerParts['quality'] = 1.0;
                }
            }
            unset($tmpParts);
            $returnedParts[] = $headerParts;
            unset($headerParts);
        }
        unset($tmpServer);
        return $returnedParts;
    }


    /**
     * Process a request header and return the one with best quality
     *
     * @param array $qualityParts
     * @param string $name
     * @return string
     */
    protected final function _getBestQuality(array $qualityParts, $name) {
        $i = 0;
        $quality = 0.0;
        $selectedName = '';

        foreach ($qualityParts as $accept) {
            if ($i == 0) {
                $qulity = (double)$accept['quality'];
                $selectedName = $accept[$name];
            }else{
                $acceptQuality = (double)$accept['quality'];
                if ($acceptQuality > $quality) {
                    $quality = $acceptQuality;
                    $selectedName = $accept[$name];
                }
            }

            $i++;
        }

        return $selectedName;
    }



    /**
     * Gets array with mime/types and their quality accepted by the browser/client from $_SERVER["HTTP_ACCEPT"]
     *
     * @return array
     */
    public function getAcceptableContent() {
        //return explode(',', $this->_swooleRequest->header['accept']);
        return $this->_getQualityHeader('accept', 'accept');
    }


    /**
     * Gets best mime/type accepted by the browser/client from $_SERVER["HTTP_ACCEPT"]
     *
     * @return string
     */
    public function getBestAccept() {
        /*$arr = explode(',', $this->_swooleRequest->header['accept']);
        return current($arr);*/
        return $this->_getBestQuality($this->getAcceptableContent(), 'accept');
    }


    /**
     * Gets charsets array and their quality accepted by the browser/client from $_SERVER["HTTP_ACCEPT_CHARSET"]
     *
     * @return array
     */
    public function getClientCharsets() {
        return $this->_getQualityHeader('accept-charset', 'charset');
    }


    /**
     * Gets best charset accepted by the browser/client from $_SERVER["HTTP_ACCEPT_CHARSET"]
     *
     * @return string
     */
    public function getBestCharset() {
        return $this->_getBestQuality($this->getClientCharsets(), 'charset');
    }


    /**
     * Gets languages array and their quality accepted by the browser/client from _SERVER["HTTP_ACCEPT_LANGUAGE"]
     *
     * @return array
     */
    public function getLanguages() {
        //return explode(',', $this->_swooleRequest->header['accept-language']);
        return $this->_getQualityHeader('accept-language', 'language');
    }


    /**
     * Gets best language accepted by the browser/client from $_SERVER["HTTP_ACCEPT_LANGUAGE"]
     *
     * @return string
     */
    public function getBestLanguage() {
        /*$arr = explode(',', $this->_swooleRequest->header['accept-language']);
        return current($arr);*/
        return $this->_getBestQuality($this->getLanguages(), 'language');
    }


    /**
     * Gets auth info accepted by the browser/client from $_SERVER["PHP_AUTH_USER"]
     *
     * @return array
     */
    public function getBasicAuth() {
        if(($usename = $this->getServer('php_auth_user')) && ($password = $this->getServer('php_auth_pw'))){
            return [
                'username' => $usename,
                'password' => $password,

            ];
        }

        return [];
    }


    /**
     * Gets auth info accepted by the browser/client from $_SERVER["PHP_AUTH_DIGEST"]
     *
     * @return array
     */
    public function getDigestAuth() {
        $auth = [];
        if($digest= $this->getServer('php_auth_digest')){
            $matches = [];
            if(!preg_match_all("#(\\w+)=(['\"]?)([^'\" ,]+)\\2#",$digest,$matches,2)){
                return $auth;
            }

            if(is_array($matches)){
                foreach($matches as $match){
                    $auth[$matches[1]] = $match[3];
                }
            }
        }

        return $auth;
    }


    /**
     * Gets HTTP URI which request has been made
     *
     * @return string
     */
    public final function getURI() {
        if($uri = $this->getServer('request_uri')){
            return $uri;
        }

        return '';
    }



    /**
     * Gets content type which request has been made
     *
     * @return string|null
     */
    public function getContentType() {
        if($contentType = $this->getServer('content_type')) {
            return $contentType;
        }elseif ($contentType = $this->getHeader('content-type')) {
            return $contentType;
        }

        return null;
    }


    /**
     * 获取请求的uuid
     * @return string
     */
    public function getRequestUuid() {
        if(is_null($this->_requestUuid)) {
            $this->_requestUuid = SwooleServer::makeRequestUuid($this->_swooleRequest);
        }

        return $this->_requestUuid;
    }


    /**
     * 设置请求的uuid
     * @param string $uuid
     */
    public function setRequestUuid($uuid='') {
        if(!empty($uuid) && is_string($uuid)) $this->_requestUuid = $uuid;
    }


    /**
     * 设置请求处理耗时,毫秒
     * @param int $millisecond
     */
    public function setUseMillisecond(int $millisecond = 0) {
        $this->_useMillisecond = $millisecond;
    }


    /**
     * 获取请求处理耗时,毫秒
     * @return int
     */
    public function getUseMillisecond() {
        return $this->_useMillisecond;
    }


    /**
     * 获取完整URL
     * @param bool $hasQuery 是否包含get参数
     * @return string
     */
    public final function getURL($hasQuery=false) {
        $scheme = $this->getScheme();
        $host = $this->getHttpHost();
        $uri = $this->getURI();

        $url = "{$scheme}://{$host}{$uri}";
        if($hasQuery && isset($this->_swooleRequest->server['query_string']) && !empty($this->_swooleRequest->server['query_string'])) {
            $url .= '?' . $this->_swooleRequest->server['query_string'];
        }
        unset($scheme, $host, $uri);

        return $url;
    }


}