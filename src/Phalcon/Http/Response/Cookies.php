<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/26
 * Time: 17:08
 * Desc: -
 */


namespace Lkk\Phalwoo\Phalcon\Http\Response;

use Phalcon\DiInterface;
use Phalcon\Http\CookieInterface;
use Phalcon\Http\Response\Cookies as PhalconCookies;
use Phalcon\Http\Response\Exception;
use Phalcon\Http\Response\CookiesInterface;
use Phalcon\Di\InjectionAwareInterface;
use Lkk\Phalwoo\Phalcon\Http\Cookie;
use Lkk\Phalwoo\Phalcon\Http\HttpTrait;


class Cookies extends PhalconCookies implements CookiesInterface, InjectionAwareInterface {

    use HttpTrait;


    /**
     * Sets a cookie to be sent at the end of the request
     * This method overrides any cookie set before with the same name
     *
     * @param string $name
     * @param mixed $value
     * @param int $expire
     * @param string $path
     * @param bool $secure
     * @param string $domain
     * @param bool $httpOnly
     * @param bool $encrypt 是否加密
     * @return Cookies
     */
    public function set($name, $value = null, $expire = 0, $path = "/", $secure = null, $domain = null, $httpOnly = null, $encrypt=null) {
        if($expire >0) $expire += time();
        if(!is_bool($encrypt)) {
            $encryption = $this->_useEncryption;
        }else{
            $encryption = $encrypt;
        }

        /** @var CookieInterface $cookie */
        $cookie = isset($this->_cookies[$name]) ? $this->_cookies[$name] : null;
        if(!$cookie){
            /** @var Cookie $cookie */
            $cookie = $this->_dependencyInjector->get(Cookie::class, [$name, $value, $expire, $path, $secure, $domain, $httpOnly]);
            $cookie->setDi($this->_dependencyInjector);

            if($encryption){
                $cookie->useEncryption($encryption);
            }

            $this->_cookies[$name] = $cookie;
        }else{
            $cookie->setValue($value);
            $cookie->setExpiration($expire);
            $cookie->setPath($path);
            $cookie->setSecure($secure);
            $cookie->setDomain($domain);
            $cookie->setHttpOnly($httpOnly);
        }

        if ($this->_registered === false) {
            /** @var DiInterface $di */
            $di = $this->_dependencyInjector;

            if (!is_object($di)) {
                throw new Exception("A dependency injection object is required to access the 'response' service");
            }

            $response = $di->getShared('response');

            $response->setCookies($this);

            $this->_registered = true;
        }

        return $this;
    }


    /**
     * Gets a cookie from the bag
     *
     * @param string $name
     * @param bool $encrypt
     * @return \Phalcon\Http\CookieInterface
     */
    public function get($name, $encrypt=null) {
        $cookie = $this->_cookies[$name] ?? null;
        if(!empty($cookie) && $cookie instanceof Cookie ) {
            return $cookie;
        }

        /** @var DiInterface $di */
        $di = $this->_dependencyInjector;

        if (!is_object($di)) {
            throw new Exception("A dependency injection object is required to access the 'response' service");
        }

        /** @var Cookie $cookie */
        $cookie = $di->get(Cookie::class, [$name]);
        $cookie->setDi($di);

        $encryption = $this->_useEncryption;
        $session = $di->getShared('session');
        if ($session->isStarted() && $session->getId()) {
            $definition = $session->get('_PHCOOKIE_'  .$name);
            if (isset($definition['encrypt'])) {
                $encryption = $definition['encrypt'];
            }
        }elseif (is_bool($encrypt)) {
            $encryption = $encrypt;
        }

        if($encryption){
            $cookie->useEncryption($encryption);
        }
        $this->_cookies[$name] = $cookie;

        return $cookie;
    }

    /**
     * Check if a cookie is defined in the bag or exists in the _COOKIE superglobal
     *
     * @param string $name
     * @return bool
     */
    public function has($name) {
        if (isset($this->_cookies[$name])) {
            return true;
        }

        $swooleRequest = $this->getSwooleRequest();
        if(isset($swooleRequest->cookie[$name])){
            return true;
        }

        return false;
    }



    /**
     * Sends the cookies to the client
     * Cookies aren't sent if headers are sent in the current request
     *
     * @return bool
     */
    public function send() {
        if(!empty($this->_cookies)) {
            foreach($this->_cookies as $cookie) {
                $cookie->send();
            }

            $this->_cookies = [];
        }

        return true;
    }


    /**
     * 统计cookies数量
     * @return int
     */
    public function count() {
        return count($this->_cookies);
    }



}


