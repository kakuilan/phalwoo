<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/12
 * Time: 21:01
 * Desc: -SWOOLE服务器
 */


namespace Lkk\Phalwoo\Server;

use Lkk\LkkService;
use Lkk\Helpers\ValidateHelper;


class SwooleServer extends LkkService {


    public $conf; //服务配置
    private $server; //swoole_server对象
    private $events; //swoole_server事件
    private $requests; //请求资源
    private $splqueue; //标准库队列,非持久化工作
    private $redqueue; //redis持久化队列

    private $servName; //服务名
    private $listenIP; //监听IP
    private $listenPort; //监听端口

    //定时任务管理器
    private $timerTaskManager;

    //命令行操作列表
    public static $cliOperations = [
        'status',
        'start',
        'stop',
        'restart',
        'reload',
        'kill',
    ];
    private static $cliOperate; //当前命令操作
    private static $daemonize; //是否以守护进程启动
    private static $pidFile;   //pid文件路径


    /**
     * 构造函数
     * SwooleServer constructor.
     * @param array $vars
     */
    public function __construct(array $vars = []) {
        parent::__construct($vars);

    }


    /**
     * 获取SWOOLE服务
     * @return mixed
     */
    public static function getServer() {
        return (is_null(self::$instance) || !is_object(self::$instance)) ? null : self::$instance->server;
    }


    /**
     * 获取定时任务管理器
     * @return mixed
     */
    public static function getTimerTaskManager() {
        return (is_null(self::$instance) || !is_object(self::$instance)) ? null : self::$instance->timerTaskManager;
    }


    /**
     * 设置内置队列对象
     */
    private function setSplQueue() {
        $this->splqueue = new \SplQueue();
        //设置迭代后数据删除
        $this->splqueue->setIteratorMode(\SplDoublyLinkedList::IT_MODE_FIFO | \SplDoublyLinkedList::IT_MODE_DELETE);
    }


    /**
     * 获取内置队列对象
     * @return mixed
     */
    public static function getSplQueue() {
        return (is_null(self::$instance) || !is_object(self::$instance)) ? null : self::$instance->splqueue;
    }


    /**
     * 设置redis队列对象
     */
    protected function setRedQueue() {
        //默认使用工作流队列,子类可自行更改
        $this->redqueue = RedisQueue::getQueueObject(RedisQueue::APP_WORKFLOW_QUEUE_NAME, []);
    }


    /**
     * 获取redis队列对象
     * @return mixed
     */
    public static function getRedQueue() {
        return (is_null(self::$instance) || !is_object(self::$instance)) ? null : self::$instance->redqueue;
    }



}