<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/27
 * Time: 17:21
 * Desc: -工作流服务基类
 */


namespace Lkk\Phalwoo\Server;

use Lkk\LkkService;

class WorkFlow extends LkkService {

    //默认工作标识
    public static $defaultKey = 'default';

    protected $isDoings = [];

    public function __construct(array $vars = []) {
        parent::__construct($vars);
    }


    /**
     * 设置某项工作正执行
     * @param bool   $status 状态
     * @param string $key 工作标识
     */
    public function setDoing(bool $status, string $key='') {
        if(empty($key)) $key = self::$defaultKey;

        $this->isDoings[$key] = $status;
    }


    /**
     * 检查某项工作是否正执行
     * @param string $key 工作标识
     *
     * @return mixed
     */
    public function chkDoing(string $key='') {
        if(empty($key)) $key = self::$defaultKey;

        return $this->isDoings[$key] ?? false;
    }


}