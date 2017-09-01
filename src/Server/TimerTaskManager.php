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
use Lkk\Helpers\CommonHelper;

class TimerTaskManager extends LkkService {

    //定时任务
    public $timerTasks;

    //初始定时器ID
    private $timerId = 0;


    public function __construct(array $vars = []) {
        parent::__construct($vars);

        //TODO 读数据表中的任务,加进来

    }


    /**
     * 获取管理的定时器ID
     * @return int
     */
    public function getTimerId() {
        return $this->timerId;
    }


    /**
     * 初始化[单个]任务数据
     * @param array $taskData
     */
    protected function initTaskData(array &$taskData) {
        //以下涉及时间的都为毫秒
        $taskProperty = [
            'run_max_exec'      => 0, //最多执行次数,0为无限制
            'run_now_exec'      => 0, //现已执行次数
            'run_interval_time' => 0, //间隔时间,int类型:<259200000(30天的毫秒)或具体某个时间戳;string类型:crontab格式,会把解析结果保存到下面字段
            'run_crontab_time'  => [], //['minute'=>[],'hour'=>[],'day'=>[],'month'=>[],'week'=>[]]
            'run_delay_time'    => 0, //延迟执行时间
            'run_endtime'       => 0, //结束时间
            'run_startime'      => CommonHelper::getMillisecond(), //开始时间
            'run_nexttime'      => 0, //下次执行时间
            'run_lasttime'      => 0, //上次执行时间
        ];

    }


    /**
     * 获取日期数供crontab用
     * @return array
     */
    public static function getDates4Cron() {
        $dates = explode('-', date('i-G-j-n-w'));
        return [
            'minute'    => $dates[0],
            'hour'      => $dates[1],
            'day'       => $dates[2],
            'month'     => $dates[3],
            'week'      => $dates[4],
        ];
    }



    /**
     * 开始定时任务
     */
    public function startTimerTasks() {
        //定时器/秒
        $this->deliveryTimerTask();
        $this->timerId = swoole_timer_tick(200, function () {
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