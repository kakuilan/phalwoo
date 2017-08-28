<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/26
 * Time: 10:29
 * Desc: -重写Phalcon的Cookie类
 */


namespace Lkk\Phalwoo\Phalcon\Http;

use Phalcon\DiInterface;
use Phalcon\Di\InjectionAwareInterface;
use Phalcon\Http\CookieInterface;
use Phalcon\Http\Cookie\Exception;
use Phalcon\Http\Cookie as PhalconCookie;
use Phalcon\Session\AdapterInterface;

class Cookie extends PhalconCookie implements CookieInterface, InjectionAwareInterface {

    use HttpTrait;


    /**
     * Readed
     *
     * @var boolean
     * @access protected
     */
    protected $_readed = false;

    /**
     * Restored
     *
     * @var boolean
     * @access protected
     */
    protected $_restored = false;

    /**
     * Use Encryption?
     *
     * @var boolean
     * @access protected
     */
    protected $_useEncryption = false;

    /**
     * Dependency Injector
     *
     * @var null|\Phalcon\DiInterface
     * @access protected
     */
    protected $_dependencyInjector;

    /**
     * Filter
     *
     * @var null|\Phalcon\FilterInterface
     * @access protected
     */
    protected $_filter;

    /**
     * Name
     *
     * @var null|string
     * @access protected
     */
    protected $_name;

    /**
     * Value
     *
     * @var null|string
     * @access protected
     */
    protected $_value;

    /**
     * Expire
     *
     * @var null|int
     * @access protected
     */
    protected $_expire;

    /**
     * Path
     *
     * @var string
     * @access protected
     */
    protected $_path = '/';

    /**
     * Domain
     *
     * @var null|string
     * @access protected
     */
    protected $_domain;

    /**
     * Secure
     *
     * @var null|boolean
     * @access protected
     */
    protected $_secure;

    /**
     * HTTP Only?
     *
     * @var boolean
     * @access protected
     */
    protected $_httpOnly = true;


    /**
     * Returns the cookie's value
     *
     * @param string|array $filters
     * @param string $defaultValue
     * @return mixed
     */
    public function getValue($filters = null, $defaultValue = null) {
        if ($this->_restored) {
            $this->restore();
        }

        if ($this->_readed !== false) {
            return $this->_value;
        }

        $swooleRequest = $this->getSwooleRequest();
        if (!isset($swooleRequest->cookie[$this->_name]) || !($value = $swooleRequest->cookie[$this->_name])) {
            return $defaultValue;
        }

        if ($this->_useEncryption) {
            $di = $this->_dependencyInjector;
            if (!is_object($di)) {
                throw new Exception('A dependency injection object is required to access the \'filter\' service');
            }

            $crypt = $di->getShared('crypt');
            $decryptedValue = $crypt->decryptBase64($value);
        } else {
            $decryptedValue = $value;
        }
        $this->_value = $decryptedValue;

        if ($filters !== null) {
            $filter = $this->_filter;
            if (!is_object($filter)) {
                if (!isset($di)) {
                    $di = $this->_dependencyInjector;
                    if (!is_object($di)) {
                        throw new Exception('A dependency injection object is required to access the \'filter\' service');
                    }
                }

                $filter        = $di->getShared('filter');
                $this->_filter = $filter;
            }

            return $filter->sanitize($decryptedValue, $filters);
        }

        return $decryptedValue;
    }


    /**
     * Sends the cookie to the HTTP client
     * Stores the cookie definition in session
     *
     * @return CookieInterface
     */
    public function send() {
        $name     = $this->_name;
        $value    = $this->_value;
        $expire   = $this->_expire;
        $domain   = $this->_domain;
        $path     = $this->_path;
        $secure   = $this->_secure;
        $httpOnly = $this->_httpOnly;

        /** @var DiInterface $di */
        $di = $this->_dependencyInjector;

        if (!is_object($di)) {
            throw new Exception("A dependency injection object is required to access the 'session' service");
        }

        $session = $di->getShared('session');
        if($session->isStarted() && $session->getWritable()) {
            $definition = ['encrypt' => $this->_useEncryption];
            if ($expire != 0) {
                $definition['expire'] = $expire;
            }

            if (!empty($path)) {
                $definition['path'] = $path;
            }

            if (!empty($domain)) {
                $definition['domain'] = $domain;
            }

            if (!empty($secure)) {
                $definition['secure'] = $secure;
            }

            if (!empty($httpOnly)) {
                $definition['httpOnly'] = $httpOnly;
            }

            $session->set('_PHCOOKIE_' . $name, $definition);
        }

        if ($this->_useEncryption) {
            if (!empty($value)) {
                if (!is_object($di)) {
                    throw new Exception("A dependency injection object is required to access the 'filter' service");
                }

                $crypt = $di->getShared('crypt');
                $encryptValue = $crypt->encryptBase64((string)$value);
            } else {
                $encryptValue = $value;
            }
        } else {
            $encryptValue = $value;
        }

        $this->getSwooleResponse()->cookie($name, $encryptValue, $expire, $path, $domain, $secure, $httpOnly);

        return $this;
    }


    /**
     * Reads the cookie-related info from the SESSION to restore the cookie as it was set
     * This method is automatically called internally so normally you don't need to call it
     *
     * @return CookieInterface
     */
    public function restore() {
        if (!$this->_restored) {
            $di = $this->_dependencyInjector;
            if (is_object($di)) {
                $session = $di->getShared('session');
                if ($session->isStarted()) {
                    $definition = $session->get('_PHCOOKIE_'  .$this->_name);
                    if (is_array($definition)) {

                        if (isset($definition['expire'])) {
                            $this->_expire = $definition['expire'];
                        }

                        if (isset($definition['domain'])) {
                            $this->_domain = $definition['domain'];
                        }

                        if (isset($definition['path'])) {
                            $this->_path = $definition['path'];
                        }

                        if (isset($definition['secure'])) {
                            $this->_secure = $definition['secure'];
                        }

                        if (isset($definition['httpOnly'])) {
                            $this->_httpOnly = $definition['httpOnly'];
                        }
                        if (isset($definition['encrypt'])) {
                            $this->useEncryption($definition['encrypt']);
                        }
                    }
                }
            }

            $this->_restored = true;
        }

        return $this;
    }



    /**
     * Deletes the cookie by setting an expire time in the past
     */
    public function delete() {
        $name     = $this->_name;
        $domain   = $this->_domain;
        $path     = $this->_path;
        $secure   = $this->_secure;
        $httpOnly = $this->_httpOnly;

        /** @var DiInterface $di */
        $di = $this->_dependencyInjector;
        if (is_object($di)) {
            /** @var AdapterInterface $session */
            $session = $di->getShared('session');

            if ($session->isStarted()) {
                $session->remove("_PHCOOKIE_" . $name);
            }
        }
        $this->_value = null;

        $this->getSwooleResponse()->cookie($name, null, time() - 691200, $path, $domain, $secure, $httpOnly);
    }





}

