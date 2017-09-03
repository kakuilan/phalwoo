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
use Lkk\Helpers\ValidateHelper;
use Cron\CronExpression;

class TimerTaskManager extends LkkService {

    //定时任务
    public $timerTasks = [];

    //初始定时器ID
    protected $timerId = 0;


    //crontab规则对象
    protected $cronExpres = [];
    public static $maxIntervalTime  = 2592000; //最大间隔时间,30天
    public static $maxDelayTime     = 2592000; //最大延后时间,,30天
    public static $defIntervalTime  = 300; //默认延迟时间,5分钟


    public function __construct(array $vars = []) {
        parent::__construct($vars);

        //TODO 读数据表中的任务,加进来

        $this->checkTimerTasks();

    }


    /**
     * 获取管理的定时器ID
     * @return int
     */
    public function getTimerId() {
        return $this->timerId;
    }


    /**
     * 获取定时任务列表
     * @return array
     */
    public function getTimerTasks() {
        return $this->timerTasks;
    }


    /**
     * 获取crontab规则处理对象
     * @param string $expres crontab规则串
     *
     * @return mixed
     */
    protected function getCronExpre(string $expres) {
        $key = md5($expres);

        if(!isset($this->cronExpres[$key])) {
            $this->cronExpres[$key] = CronExpression::factory($expres);
        }

        return $this->cronExpres[$key];
    }


    /**
     * 初始化[单个]任务数据
     * @param array $taskData
     */
    protected function initTaskData(array &$taskData) {
        $now = number_format(microtime(true), 1, '.','');

        //以下涉及时间的都为秒,精确到1位小数
        $taskProperty = [
            'run_max_exec'      => 0, //最多执行次数,0为无限制
            'run_now_exec'      => 0, //现已执行次数
            'run_crontab_time'  => '', //string类型:crontab格式,该规则会比run_interval_time优先
            'run_interval_time' => 0, //间隔时间,int类型:<self::$maxIntervalTime或具体某个时间戳
            'run_delay_time'    => 0, //延迟执行时间
            'run_endtime'       => 0, //结束时间,0为无限制
            'run_startime'      => $now, //开始时间
            'run_nexttime'      => $now, //下次执行时间
            'run_lasttime'      => 0, //上次执行时间
        ];
        $taskData = array_merge($taskProperty, $taskData);

        while (true) {
            //检查cron
            if(is_string($taskData['run_crontab_time']) && CronExpression::isValidExpression($taskData['run_crontab_time'])) {
                $taskData['run_crontab_time'] = trim($taskData['run_crontab_time']);
                $cronExpre = $this->getCronExpre($taskData['run_crontab_time']);
                $taskData['run_nexttime'] = $cronExpre->getNextRunDate()->getTimestamp();
            }else{
                $taskData['run_crontab_time'] = '';
            }

            //检查interval
            if(empty($taskData['run_crontab_time'])) {
                if(is_string($taskData['run_interval_time'])) $taskData['run_interval_time'] = ValidateHelper::isDate2time($taskData['run_interval_time']);
                if(is_numeric($taskData['run_interval_time']) && $taskData['run_interval_time']>0) {
                    if($taskData['run_interval_time'] <= self::$maxIntervalTime) {
                        $taskData['run_nexttime'] += $taskData['run_interval_time'];
                    }elseif ($taskData['run_interval_time'] >= $now) { //具体时间戳,只执行一次
                        $taskData['run_max_exec'] = 1;
                        $taskData['run_nexttime'] = $taskData['run_interval_time'];
                        $taskData['run_endtime'] = $taskData['run_interval_time'];
                    }elseif ($taskData['run_interval_time'] > self::$maxIntervalTime &&$taskData['run_interval_time'] < $now) {
                        $this->setError('run_interval_time < now');
                        $taskData = [];
                        break;
                    }
                }else{
                    $this->setError('run_interval_time error');
                    $taskData = [];
                    break;
                }
            }

            //检查delay
            if(is_numeric($taskData['run_delay_time']) && $taskData['run_delay_time']>0) {
                if($taskData['run_delay_time'] <= self::$maxDelayTime) {
                    $taskData['run_nexttime'] += $taskData['run_delay_time'];
                }else{
                    $taskData['run_delay_time'] = 0;
                }
            }else{
                $taskData['run_delay_time'] = 0;
            }

            //检查结束时间
            if(!is_numeric($taskData['run_endtime']) || $taskData['run_endtime']<0) {
                $taskData['run_endtime'] = 0;
            }elseif ($taskData['run_endtime']>0 && $taskData['run_endtime'] < $now) {
                $this->setError('run_endtime < now.');
                $taskData = [];
                break;
            }

            break;
        }

        return $taskData;
    }



    /**
     * 检查定时任务列表
     * @return int
     */
    public function checkTimerTasks() {
        $taskNum = 0;
        if(!empty($this->timerTasks)) {
            foreach ($this->timerTasks as $k=>&$taskData) {
                $title = $taskData['message']['title'] ?? $k;
                $taskData = $this->initTaskData($taskData);
                if(empty($taskData)) {
                    echo "check timer [$title] has error: {$this->error}\r\n";
                    unset($this->timerTasks[$k]);
                    continue;
                }

                $taskNum++;
            }
        }

        echo "loaded timer tasks total:[$taskNum]\r\n";

        return $taskNum;
    }



    /**
     * 开始定时任务
     */
    public function startTimerTasks() {
        //定时器/秒
        $this->deliveryTimerTask();
        $this->timerId = swoole_timer_tick(100, function () {
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
        $totalNum = count($this->timerTasks);
        $succeNum = 0;
        if(!empty($this->timerTasks)) {
            $now = number_format(microtime(true), 1, '.','');
            $second = date('s');
            $milSecond = CommonHelper::getMillisecond();
            foreach ($this->timerTasks as $k=> &$taskData) {
                //检查结束时间
                if($taskData['run_endtime'] && $taskData['run_endtime'] < $now) {
                    echo "delete timer [{$taskData['message']['title']}] error:run_endtime<now \r\n";
                    unset($this->timerTasks[$k]);
                    continue;
                }

                //检查执行次数
                if($taskData['run_max_exec'] && $taskData['run_now_exec'] >= $taskData['run_max_exec']) {
                    echo "delete timer [{$taskData['message']['title']}] error:run_now_exec > run_max_exec \r\n";
                    unset($this->timerTasks[$k]);
                    continue;
                }

                //nexttime
                if($taskData['run_nexttime'] > $now) {
                    continue;
                }

                //crontab,分钟
                if($taskData['run_crontab_time'] && $second=='00') {
                    $cron = $this->getCronExpre($taskData['run_crontab_time']);
                    if(!$cron->isDue()) continue;

                    $taskData['run_now_exec']++;
                    $taskData['run_lasttime'] = $now;
                    $taskData['run_nexttime'] = $cron->getNextRunDate()->getTimestamp();
                    unset($cron);
                }elseif ($taskData['run_interval_time']) {
                    $taskData['run_now_exec']++;
                    $taskData['run_lasttime'] = $now;
                    if($taskData['run_interval_time'] <= self::$maxIntervalTime) {
                        $taskData['run_nexttime'] += $taskData['run_interval_time'];
                    }else{
                        $taskData['run_endtime'] = $taskData['run_nexttime'] = 0;
                    }
                }else{
                    continue;
                }

                $res = SwooleServer::getServer()->task($taskData);
                if($res) $succeNum++;
            }
        }

        //echo "delivery timer tasks total:[$totalNum] sucess:[$succeNum]\r\n";

        return $succeNum;
    }


    /**
     * 新加一个定时任务
     * @param array $taskData 任务相关数据,形如['type'=.'xx','message'=>[xx]]
     *
     * @return bool|int
     */
    public function addTask($taskData) {
        $taskData = $this->initTaskData($taskData);
        if(empty($taskData)) return false;

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