<?php
/**
 * Created by PhpStorm.
 * User: kakuilan@163.com
 * Date: 17-9-12
 * Time: 下午1:44
 * Desc:
 */


namespace Lkk\Phalwoo\Server\Component\Pool\Adapter;

use Lkk\Phalwoo\Server\Component\Pool\Adapter;
use Lkk\Phalwoo\Server\ServerConst;
use Lkk\Phalwoo\Server\SwooleServer;
use Lkk\Phalwoo\Server\Component\Client\Mysql as Driver;
use Lkk\Concurrent\Promise;

class Mysql extends Adapter {

    /**
     * @var Driver
     */
    private $sync;

    /**
     * 上次同步连接的时间
     * @var int
     */
    private $sync_first_connect_time = 0;

    public function __construct($conf) {
        $this->conf = $conf;
        $this->conf['name'] = $conf['name'] ?? __FILE__;
        $this->conf['size'] = $conf['size'] ?? 5;
        parent::__construct($this->conf['name'], $this->conf['size']);
        unset($conf);
    }


    public function init() {
        if(SwooleServer::isWorker()) {
            for($i = 0; $i < $this->size; $i ++) {
                $this->newItem($i + 1);
            }
        }

        $this->reconnSync();
    }


    /**
     * 重建同步连接
     * @return Driver|mixed
     */
    protected function reconnSync() {
        if(is_object($this->sync)) {
            $this->sync->close();
        }

        $this->sync = new Driver($this->conf['args'], ServerConst::MODE_SYNC);

        $connTimeout = $this->conf['conn_timeout'] ?? 0;
        $this->sync->connect(0, $connTimeout);
        $this->sync_first_connect_time = time();

        return $this->sync;
    }


    /**
     * 弹出一个空闲item
     * @param bool $force_sync      强制使用同步模式
     * @return mixed
     */
    public function pop($force_sync = false) {
        if(SwooleServer::isWorker() && !$force_sync) {
            while( !$this->idle_queue->isEmpty() ) {
                $driver = $this->idle_queue->dequeue();
                if( $driver->isClose() ) {
                    continue;
                }
                return $driver;
            }

            $promise = new Promise();
            $this->waiting_tasks->enqueue($promise);
            return $promise;
        }else {
            $now = time();
            $waitTimeout = $this->conf['args']['wait_timeout'] ?? 3600;
            $maxTime = $this->sync_first_connect_time + $waitTimeout;

            if(empty($this->sync_first_connect_time) || !($now>=$this->sync_first_connect_time && $now<$maxTime)) {
                $this->reconnSync();
            }

            return $this->sync;
        }
    }


    protected function newItem($id) {
        $driver = new Driver($this->conf['args']);
        $driver->addPool($this);
        $driver->connect($id)->then(function() use ($driver){
            $this->idle_queue->enqueue($driver);
            if( $this->waiting_tasks->count() > 0 ) {
                $this->doTask();
            }
        }, function() use ($id){
            $this->newItem($id);
        });
        unset($driver);
    }


    protected function doTask() {
        $promise = $this->waiting_tasks->dequeue();
        $driver = $this->idle_queue->dequeue();
        $promise->resolve($driver);

        unset($promise, $driver);
    }


    /**
     * 归还一个item
     * @param $item
     * @param bool $close 是否关闭
     */
    public function push($item, $close = false) {
        if($close) {
            $this->newItem($item->id);
            unset($item);
            return;
        }
        $this->idle_queue->enqueue($item);
        if( $this->waiting_tasks->count() > 0 ) {
            $this->doTask();
        }
        return;
    }
}