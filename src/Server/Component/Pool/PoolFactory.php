<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/9/6
 * Time: 21:36
 * Desc: -连接池工厂类
 */


namespace Lkk\Phalwoo\Server\Component\Pool;

class PoolFactory {

    /**
     * 根据指定的配置数据创建一个连接池实例
     * @param $config   array   配置数据, 键值对存储
     * @return Adapter | null  创建好的连接池, 连接池类型不存在返回null
     */
    public static function getInstance($config) {
        if( empty($config) || !isset($config['type'])) {
            return null;
        }
        $type = ucfirst(strtolower($config['type']));
        $class_name = __NAMESPACE__ . '\\Adapter\\' . $type;
        if( !class_exists($class_name) ) {
            return null;
        }

        return new $class_name($config);
    }
}