<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/9/19
 * Time: 19:15
 * Desc: -数据模型的异步结果类
 */


namespace Lkk\Phalwoo\Phalcon\Mvc\Model\Resultset;

use ArrayObject;
use Phalcon\Mvc\Model\ResultsetInterface;


class Async extends ArrayObject implements ResultsetInterface {

    const ASYNC_OBJECTS = 3;


    const ASYNC_ARRAYS = 4;


    protected $_result = false;


    protected $_cache;


    protected $_isFresh = true;


    protected $_pointer = 0;


    protected $_count;


    protected $_activeRow = null;


    protected $_rows = null;


    protected $_row = null;


    protected $_asyncResMode = self::ASYNC_OBJECTS;

    /**
     * Returns the internal type of data retrieval that the resultset is using
     *
     * @return int
     */
    public function getType() {
        return $this->_asyncResMode;
    }


    /**
     * Get first row in the resultset
     *
     * @return ArrayObject
     */
    public function getFirst() {
        return reset($this);
    }


    /**
     * Get last row in the resultset
     *
     * @return ArrayObject
     */
    public function getLast() {
        return end($this);
    }


    /**
     * Get current row in the resultset
     *
     * @return ArrayObject
     */
    public function getCurrent() {
        return current($this);
    }


    /**
     * Set if the resultset is fresh or an old one cached
     *
     * @param bool $isFresh
     */
    public function setIsFresh($isFresh) {
        if (!is_bool($isFresh)) {
            throw new \Exception('Invalid parameter type.');
        }

        $this->_isFresh = $isFresh;
    }

    /**
     * Tell if the resultset if fresh or an old one cached
     *
     * @return bool
     */
    public function isFresh() {
        return $this->_isFresh;
    }

    /**
     * Returns the associated cache for the resultset
     *
     * @return \Phalcon\Cache\BackendInterface
     */
    public function getCache() {
        //TODO
    }

    /**
     * Returns a complete resultset as an array, if the resultset has a big number of rows
     * it could consume more memory than currently it does.
     *
     * @return mixed|array
     */
    public function toArray() {
        return $this->_count ? $this : [];
    }


    /**
     * Async constructor.
     *
     * @param array $input SQL查询结果,二维数组
     */
    function __construct(array $input=[]) {
        $this->_count = count($input);
        foreach ($input as &$item) {
            if(is_array($item)) $item = new ArrayObject($item, ArrayObject::ARRAY_AS_PROPS);
        }

        parent::__construct($input, ArrayObject::ARRAY_AS_PROPS);
    }


}