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

    private $isDoing = false;

    public function __construct(array $vars = []) {
        parent::__construct($vars);
    }


    public function setDoing(bool $status) {
        $this->isDoing = $status;
    }


    public function chkDoing() {
        return $this->isDoing;
    }


}