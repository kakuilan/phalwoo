<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/27
 * Time: 18:27
 * Desc: -SESSION工作流
 */


namespace Tests\Server;

use Lkk\Phalwoo\Server\WorkFlow;
use Lkk\Phalwoo\Server\SwooleServer;
use Lkk\Phalwoo\Phalcon\Session\Adapter;

class WorkFlowSession extends WorkFlow {

    public function __construct(array $vars = []) {
        parent::__construct($vars);
    }


    public function writeSession() {
        if($this->chkDoing()) return false;
        $this->setDoing(true);
        $num = 10000;
        $i = $succ = 0;
        $redis = new \Redis();
        $redis->pconnect('localhost', '6379', 2.5);
        $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
        $redis->select(1);
        $inerQueue = SwooleServer::getInerQueue();
        $state = $inerQueue->stats();

        if($state['queue_num']<=0) {
            $this->setDoing(false);
            return false;
        }

        $redis->multi();
        while ($i<$num) {
            $item = $inerQueue->pop();
            if(empty($item)) {
                break;
            }elseif ($item['type']=='session') {
                $item = $item['data'];
                if(empty($item['session'])) {
                    $res = $redis->del($item['']['key']);
                }/*elseif($item['lefttime'] ==0) { //第一次,初始为60s,防止非浏览器压测
                    $res = $redis->setex($item['key'], 60, $item['session']);
                    $succ++;
                }*/elseif($item['lefttime'] <=120) { //第二次更新为正常
                    $res = $redis->setex($item['key'], Adapter::SESSION_LIFETIME, $item['session']);
                    $succ++;
                }else{
                    $res = $redis->setex($item['key'], $item['lefttime'], $item['session']);
                    $succ++;
                }
            }else{
                $inerQueue->push($item);
            }

            $i++;
        }
        $redis->exec();
        $this->setDoing(false);

        echo "session入库完毕! succNum:[{$succ}] \r\n";

        return true;
    }


}