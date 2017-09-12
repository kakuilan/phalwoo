<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/9/6
 * Time: 21:41
 * Desc: -
 */


namespace Lkk\Phalwoo\Server\Component\Pool;

use Lkk\LkkService;

class PoolManager extends LkkService {

    /**
     * @var array 配置数据
     */
    protected $conf;

    /**
     * @var array[Adapter] 连接池的实例数组
     */
    protected $pools;

    public function __construct(array $vars = []) {
        parent::__construct($vars);
    }


    /**
     * 设置配置
     * @param array $conf
     *
     * @return bool
     */
    public function setConf(array $conf) {
        if(empty($conf)) return false;
        foreach ($conf as $name=>$item) {
            $item['name'] = $name;
            $this->conf[$name] = $item;
        }

        return true;
    }


    /**
     * 初始化全部
     */
    public function initAll() {
        foreach ($this->conf as $name => $itme) {
            $this->init($name);
        }
    }


    /**
     * 初始化一个连接池
     * @param $name     string      连接池的配置名称
     * @return bool                 创建成功返回true
     */
    public function init($name) {
        if( !isset($this->conf[$name] )) {
            return false;
        }

        if( !isset($this->pools[$name]) ) {
            $this->pools[$name] = PoolFactory::getInstance($this->conf[$name]);
            if( empty($this->pools[$name]) ) {
                return false;
            }
            $this->pools[$name]->init();
        }

        return true;
    }


    /**
     * 根据名称获取一个指定的连接池实例
     * @param $name             string      连接池的配置名称
     * @return Adapter|null
     */
    public function get($name) {
        return $this->pools[$name] ?? null;
    }



}