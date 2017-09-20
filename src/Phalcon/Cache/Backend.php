<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/9/16
 * Time: 10:07
 * Desc: -
 */


namespace Lkk\Phalwoo\Phalcon\Cache;

use Lkk\Phalwoo\Phalcon\Cache\Exception;
use Phalcon\Cache\FrontendInterface;
use Phalcon\Cache\BackendInterface;

abstract class Backend implements BackendInterface {

    /**
     * Frontend
     *
     * @var null|FrontendInterface
     * @access protected
     */
    protected $_frontend;

    /**
     * Options
     *
     * @var null|array
     * @access protected
     */
    protected $_options;

    /**
     * Prefix
     *
     * @var string
     * @access protected
     */
    protected $_prefix = '';

    /**
     * Last Key
     *
     * @var string
     * @access protected
     */
    protected $_lastKey = '';

    /**
     * Last Lifetime
     *
     * @var null|int
     * @access protected
     */
    protected $_lastLifetime = null;

    /**
     * Fresh
     *
     * @var boolean
     * @access protected
     */
    protected $_fresh = false;

    /**
     * Started
     *
     * @var boolean
     * @access protected
     */
    protected $_started = false;


    /**
     * Returns front-end instance adapter related to the back-end
     * @return null|FrontendInterface
     */
    public function getFrontend() {
        return $this->_frontend;
    }

    /**
     * @param mixed $frontend
     */
    public function setFrontend($frontend) {
        $this->_frontend = $frontend;
    }


    /**
     * Returns the backend options
     * @return array|null
     */
    public function getOptions() {
        return $this->_options;
    }

    /**
     * @param mixed $options
     */
    public function setOptions($options) {
        if (is_array($options)) {
            $this->_options = $options;
        } else {
            throw new Exception('Invalid parameter type.');
        }
    }


    /**
     * Gets the last key stored by the cache
     * @return string
     */
    public function getLastKey() {
        return $this->_lastKey;
    }

    /**
     * Sets the last key used in the cache
     * @param mixed $lastKey
     */
    public function setLastKey($lastKey) {
        if (!is_string($lastKey)) {
            throw new Exception('Invalid parameter type.');
        }

        $this->_lastKey = $lastKey;
    }

    /**
     * Phalcon\Cache\Backend constructor
     *
     * @param FrontendInterface $frontend
     * @param array $options
     */
    public function __construct(FrontendInterface $frontend, $options = null) {
        if (!is_object($frontend) ||
            $frontend instanceof FrontendInterface === false) {
            throw new Exception('Frontend must be an Object');
        }

        /**
         * A common option is the prefix
         */
        if (is_array($options) && isset($options['prefix'])) {
            $this->_prefix = $options['prefix'];
        }

        $this->_frontend = $frontend;
        $this->_options = $options;
    }

    /**
     * Starts a cache. The keyname allows to identify the created fragment
     *
     * @param int|string $keyName
     * @param int $lifetime
     * @return mixed
     */
    public function start($keyName, $lifetime = null) {
        /**
         * Get the cache content verifying if it was expired
         */
        $existingCache = $this->{'get'}($keyName, $lifetime);

        if ($existingCache === null) {
            $fresh = true;
            $this->_frontend->start();
        } else {
            $fresh = false;
        }

        $this->_fresh = $fresh;
        $this->_started = true;

        /**
         * Update the last lifetime to be used in save()
         */
        if (!is_null($lifetime)) {
            $this->_lastLifetime = $lifetime;
        }

        return $existingCache;
    }

    /**
     * Stops the frontend without store any cached content
     *
     * @param bool $stopBuffer
     */
    public function stop($stopBuffer = true) {
        if (is_bool($stopBuffer) === false) {
            throw new Exception('Invalid parameter type.');
        }

        if ($stopBuffer === true) {
            $this->_frontend->stop();
        }

        $this->_started = false;
    }

    /**
     * Checks whether the last cache is fresh or cached
     *
     * @return bool
     */
    public function isFresh() {
        return $this->_fresh;
    }

    /**
     * Checks whether the cache has starting buffering or not
     *
     * @return bool
     */
    public function isStarted() {
        return $this->_started;
    }

    /**
     * Gets the last lifetime set
     *
     * @return int
     */
    public function getLifetime() {
        return $this->_lastLifetime;
    }

}





