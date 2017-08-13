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

class Task extends LkkService {

    public function dumpTest($title='none') {
        $time = date('Y-m-d H:i:s');
        $msg = "timer task callback: time[{$time}] title[{$title}]\r\n";
        print_r($msg);
    }


}