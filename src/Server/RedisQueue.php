<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/12
 * Time: 21:47
 * Desc: -redis队列服务
 */


namespace Lkk\Phalwoo\Server;

use Lkk\LkkRedisQueueService;


class RedisQueue extends LkkRedisQueueService {

    const APP_WORKFLOW_QUEUE_NAME   = 'app_workflow'; //APP工作流队列名称
    const APP_NOTIFY_QUEUE_NAME     = 'app_notify'; //APP应用通知队列名称
    const APP_NOTIFY_RESEND_TIMES   = 50; //APP应用通知重发次数

    //队列对象
    public static $queues = [];


    /**
     * 构造函数
     * RedisQueueService constructor.
     * @param array $vars
     */
    public function __construct(array $vars = []) {
        parent::__construct($vars);
    }






}