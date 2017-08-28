<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/24
 * Time: 23:17
 * Desc: -
 */


namespace Lkk\Phalwoo\Phalcon\Session;

use Phalcon\Session\AdapterInterface;
use Phalcon\Di\InjectionAwareInterface;
use Phalcon\Session\Exception;


abstract class Adapter implements AdapterInterface, InjectionAwareInterface {

    const SESSION_ACTIVE = 2;

    const SESSION_NONE = 1;

    const SESSION_DISABLED = 0;

    const SESSION_NAME = 'PHPSESSID';


    /**
     * SESSION_ID
     *
     * @var null|string
     * @access protected
     */
    protected $_id;


    /**
     * session cookie name
     *
     * @var string
     * @access protected
     */
    protected $_name = self::SESSION_NAME;


    /**
     * Unique ID
     *
     * @var null|string
     * @access protected
     */
    protected $_uniqueId;

    /**
     * Started
     *
     * @var boolean
     * @access protected
     */
    protected $_started = false;


    /**
     * status
     *
     * @var boolean
     * @access protected
     */
    protected $_status = self::SESSION_NONE;



    /**
     * Options
     *
     * @var null|array
     * @access protected
     */
    protected $_options;


    protected $_dependencyInjector;



    /**
     * Key prefix
     * @var string
     */
    protected $_prefix = '';

    /**
     * Session lifetime
     * @var int
     */
    protected $_lifetime = 8600;


    /**
     * Phalcon\Session\Adapter\Redis constructor
     *
     * @param array $options
     */
    public function __construct(array $options = []) {
        if (is_array($options)) {
            $this->setOptions($options);
        }

    }


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
     * Sets session options
     *
     * @param array $options
     */
    public function setOptions(array $options) {
        if (!is_array($options)) {
            throw new Exception('Options must be an Array');
        }

        if (isset($options['uniqueId'])) {
            $this->_uniqueId = $options['uniqueId'];
        }

        if (isset($options['prefix'])) {
            $this->_prefix = $options['prefix'];
        }

        if (isset($options['lifetime']) && is_int($options['lifetime'])) {
            $this->_lifetime = $options['lifetime'];
        }

        if (isset($options['name']) && !empty($options['name'])) {
            $this->_name = $options['name'];
        }

        $this->_options = $options;
    }



    /**
     * Get internal options
     *
     * @return array
     */
    public function getOptions() {
        return $this->_options;
    }


    /**
     * 获取某项配置
     * @param string $name
     * @param null $default
     *
     * @return mixed|null
     */
    public function getOption($name, $default = null) {
        return $this->_options[$name] ?? $default;
    }


    /**
     * set active session id
     * @param $id
     */
    public function setId($id) {
        $this->_id = $id;
    }


    /**
     * Returns active session id
     *
     * @return string
     */
    public function getId() {
        return $this->_id;
    }

    /**
     * Check whether the session has been started
     *
     * @return bool
     */
    public function isStarted() {
        return $this->_started;
    }


    /**
     * 检查会话状态
     * @return bool|int
     */
    public function status() {
        if($this->_started) {
            return self::SESSION_ACTIVE;
        }else{
            return $this->_status;
        }
    }


    /**
     * Set session name
     *
     * @param string $name
     */
    public function setName($name) {
        if (!is_string($name) || empty($name)) {
            throw new Exception('Name must be an string');
        }

        $this->_name = $name;
    }


    /**
     * Get session name
     *
     * @return string
     */
    public function getName() {
        return $this->_name;
    }



}
