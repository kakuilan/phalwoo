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

    //redis默认配置
    public static $defaultCnf = null;


    /**
     * 构造函数
     * RedisQueueService constructor.
     * @param array $vars
     */
    public function __construct(array $vars = []) {
        parent::__construct($vars);
    }


    /**
     * 获取默认的redis配置,子类可重写
     * @return array
     */
    public static function getDefultRedisCnf() {
        $cnf = [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => null,
            'select' => null,
        ];

        return empty(self::$defaultCnf) ? $cnf : self::$defaultCnf;
    }


    /**
     * 重置默认的redis配置
     * @param array $conf
     */
    public static function resetDefultRedisCnf(array $conf) {
        self::$defaultCnf = $conf;
    }


    /**
     * 获取队列实例化对象
     * @param string $queueName 队列名
     * @param array $conf 队列初始化配置
     *
     * @return RedisQueue|mixed
     */
    public static function getQueueObject($queueName, $conf=[]) {
        if(empty($conf)) $conf = [
            'redisConf' => static::getDefultRedisCnf(),
            'transTime' => 30,
        ];
        $key = md5($queueName . json_encode($conf));

        if(!isset(self::$queues[$key]) || empty(self::$queues[$key])) {
            $queue = new RedisQueue($conf);
            $queue->newQueue($queueName);
            self::$queues[$key] = $queue;
        }else{
            $queue = self::$queues[$key];
        }

        return $queue;
    }



    /**
     * 快速添加单个消息到工作流队列
     * @param array $item 消息:例如['type'=>'register', 'data'=>[]]
     * @param array $conf 配置
     * @return array
     */
    public static function quickAddItem2WorkflowMq($item=[], $conf=[]) {
        $queue = self::getQueueObject(self::APP_WORKFLOW_QUEUE_NAME, $conf);
        $res = $queue->push($item);
        $data = [
            'result' => $res,
            'error' => $queue->error,
        ];

        return $data;
    }


    /**
     * 快速添加多个消息到工作流队列
     * @param array $items 消息数组
     * @param array $conf 配置
     * @return array
     */
    public static function quickAddMultItem2WorkflowMq($items=[], $conf=[]) {
        $queue = self::getQueueObject(self::APP_WORKFLOW_QUEUE_NAME, $conf);
        $res = $queue->pushMulti($items);
        $data = [
            'result' => $res,
            'error' => $queue->error,
        ];

        return $data;
    }


    /**
     * 快速添加单个消息到APP通知队列
     * @param array $item 消息:例如['type'=>'msg', 'data'=>[]],type类型有msg站内信,mail邮件,sms短信,wechat微信
     * @param array $conf 配置
     * @return array
     */
    public static function quickAddItem2AppNotifyMq($item=[], $conf=[]) {
        $queue = self::getQueueObject(self::APP_NOTIFY_QUEUE_NAME, $conf);
        $res = $queue->push($item);
        $data = [
            'result' => $res,
            'error' => $queue->error,
        ];

        return $data;
    }


    /**
     * 快速添加多个消息到APP用户通知队列
     * @param array $items 消息数组
     * @param array $conf 配置
     * @return array
     */
    public static function quickAddMultItem2AppNotifyMq($items=[], $conf=[]) {
        $queue = self::getQueueObject(self::APP_NOTIFY_QUEUE_NAME, $conf);
        $res = $queue->pushMulti($items);
        $data = [
            'result' => $res,
            'error' => $queue->error,
        ];

        return $data;
    }








}