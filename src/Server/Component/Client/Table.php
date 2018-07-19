<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/10/2
 * Time: 0:14
 * Desc: -共享内存表
 */


namespace Lkk\Phalwoo\Server\Component\Client;

use Swoole\Table as SwTable;

class Table {

    private $itemSize = 128;

    private $valueLeng = 10240;

    private $table;
    private static $defaultValue = 'values';


    public function __construct(int $itemSize=0, int $valueLeng=0) {
        if($itemSize) $this->itemSize = $itemSize;
        if($valueLeng) $this->valueLeng = $valueLeng;

        $this->table = new SwTable($this->itemSize);
        $this->table->column(self::$defaultValue, SwTable::TYPE_STRING, $this->valueLeng);
        $this->table->create();
    }


    /**
     * 获取某个记录/子项
     * @param string $key 记录key
     * @param string $subIndex 子项索引
     *
     * @return mixed|null
     */
    public function get(string $key, string $subIndex='') {
        $res = null;
        $values = $this->table->get($key);
        if($values) {
            $res = json_decode($values[self::$defaultValue], true);
            if($subIndex) $res = $res[$subIndex] ?? null;
        }

        return $res;
    }


    /**
     * 设置某个记录
     * @param string $key
     * @param array  $values
     *
     * @return bool
     */
    public function set(string $key, array $values) {
        $res = false;
        if(empty($key) || empty($values)) return $res;

        $array = [self::$defaultValue => json_encode($values)];
        $res = $this->table->set($key, $array);
        unset($values, $array);

        return $res;
    }


    /**
     * 设置子项
     * @param string $key
     * @param array  $array
     *
     * @return bool
     */
    public function setSubItem(string $key, array $array) {
        $res = false;
        if(empty($key) || empty($array)) return $res;

        $origin = $this->get($key);
        if(empty($origin)) $origin = [];

        $values = array_merge($origin, $array);
        $res = $this->set($key, $values);
        unset($array, $values);

        return $res;
    }


    /**
     * 删除记录/子项
     * @param string $key
     * @param string $subIndex
     *
     * @return bool
     */
    public function del(string $key, string $subIndex='') {
        $res = false;
        if(empty($key)) return $res;

        if($subIndex) { //删除子项
            $origin = $this->get($key);
            if($origin && isset($origin[$subIndex])) {
                unset($origin[$subIndex]);
                $res = $this->set($key, $origin);
            }
        }else{
            $res = $this->table->del($key);
        }

        return $res;
    }




}