<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/13
 * Time: 17:45
 * Desc: -
 */


namespace Tests\Server;

use \Lkk\LkkService;
use Tests\Server\WorkFlowSession;

class Task extends LkkService {

    public function dumpTest($title='none') {
        $time = date('Y-m-d H:i:s');
        $msg = "timer task callback: time[{$time}] title[{$title}]\r\n";
        //print_r($msg);
    }


    public function crontTimerTest($title='none') {
        $time = date('Y-m-d H:i:s');
        $msg = "timer task callback: time[{$time}] title[{$title}]\r\n";
        print_r($msg);
    }


    public function onceTimerTest($title='none') {
        $time = date('Y-m-d H:i:s');
        $msg = "timer task callback: time[{$time}] title[{$title}]\r\n";
        print_r($msg);
    }




    public function sessionTest() {
        $sessionWork = WorkFlowSession::getInstance();
        if($sessionWork->chkDoing()) {
            echo "session工作流正在处理... \r\n";
        }else{
            $sessionWork->writeSession();
        }
    }




}