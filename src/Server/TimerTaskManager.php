<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/12
 * Time: 21:06
 * Desc: -定时任务管理器
 */


namespace Lkk\Phalwoo\Server;

use Lkk\LkkService;

class TimerTaskManager extends LkkService {

    //定时任务
    public $timerTasks;

    //初始定时器ID
    private $timerId = 0;


    public function __construct(array $vars = []) {
        parent::__construct($vars);

        //TODO读数据表中的任务,加进来

        $this->startTimerTasks();
    }


    /**
     * 获取管理的定时器ID
     * @return int
     */
    public function getTimerId() {
        return $this->timerId;
    }


    /**
     * 开始定时任务
     */
    public function startTimerTasks() {
        //定时器/秒
        $this->deliveryTimerTask();
        $this->timerId = swoole_timer_tick(1000, function () {
            $this->deliveryTimerTask();
        });
    }


    /**
     * 停止定时任务
     * @return bool
     */
    public function stopTimerTasks() {
        return swoole_timer_clear($this->timerId);
    }


    /**
     * 投递定时任务
     * @return int
     */
    public function deliveryTimerTask() {
        $taskNum = 0;
        if(!empty($this->timerTasks)) {
            foreach ($this->timerTasks as $taskData) {
                if(!empty($taskData)) {
                    $res = SwooleServer::getServer()->task($taskData);
                    if($res) $taskNum++;
                }
            }
        }

        return $taskNum;
    }


    /**
     * 新加一个定时任务
     * @param array $taskData 任务相关数据,形如['type'=.'xx','message'=>[xx]]
     *
     * @return int
     */
    public function addTask($taskData) {
        return array_push($this->timerTasks, $taskData);
    }


    /**
     * 重启定时任务
     */
    public function restartTimer() {
        $this->stopTimerTasks();
        $this->startTimerTasks();
    }


}