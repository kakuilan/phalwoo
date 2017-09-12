<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/9/6
 * Time: 23:12
 * Desc: -Redis连接对象的封装
 */


namespace Lkk\Phalwoo\Server\Component\Client;

use Lkk\Phalwoo\Server\SwooleServer;
use Lkk\Phalwoo\Server\ServerConst;
use Lkk\Phalwoo\Server\Component\Pool\Adapter as PoolAdapter;
use Lkk\Phalwoo\Server\Concurrent\Promise;

class Redis {

    /**
     * 连接ID
     * @var int
     */
    public $id;

    /**
     * swoole_redis连接对象
     * @var \swoole_redis
     */
    private $db;

    /**
     * 超时时间
     * @var int
     */
    private $timeout = 3000;

    /**
     * 所属连接池
     * @var PoolAdapter
     */
    private $pool;


    /**
     * 配置
     * @var array
     */
    private $conf;

    /**
     * 同步异步模式
     * @var int
     */
    private $mode;

    /**
     * 同步Redis连接对象
     * @var \Redis
     */
    private $link;


    /**
     * Redis constructor.
     * @param $config       array       配置选项
     * @param $mode         int         模式(ServerConst中的MODE常量)
     */
    public function __construct($config,  $mode = ServerConst::MODE_ASYNC) {
        $this->conf = $config;
        $this->mode = $mode;
    }


    /**
     * 设置所属的连接池
     * @param $pool     PoolAdapter    连接池对象
     */
    public function addPool($pool) {
        $this->pool = $pool;
    }


    private function inPool($close = false) {
        if( !empty($this->pool) ) {
            $this->pool->push($this, $close);
        }
    }


    /**
     * 关闭Redis连接
     */
    public function close() {
        switch ($this->mode) {
            case ServerConst::MODE_ASYNC :
                $this->db->close();
                $this->db = null;
                $this->inPool(true);
                break;
            case ServerConst::MODE_SYNC :
                $this->link->close();
                break;
            default :
                break;
        }
    }


    /**
     * 建立Redis连接
     * @param $id           int     连接ID
     * @param $timeout      int     超时时间, 单位ms
     * @return Promise              Promise对象
     */
    public function connect($id, $timeout = 3000) {
        $this->id = $id;
        $promise = new Promise();
        $logger = SwooleServer::getLogger();
        switch ($this->mode) {
            case ServerConst::MODE_ASYNC : {
                $this->db = new \swoole_redis();
                $this->db->on("close", function(){
                    SwooleServer::getLogger()->info("Redis Close connection {$this->id}");
                    $this->connect($this->id);
                });
                $timeId = swoole_timer_after($timeout, function() use ($promise){
                    $this->close();
                    $promise->resolve([
                        'code'  => ServerConst::ERR_REDIS_TIMEOUT
                    ]);
                });
                $this->db->connect($this->conf['host'], $this->conf['port'],
                    function (\swoole_redis $client, $result) use($timeId,$promise){
                        \swoole_timer_clear($timeId);
                        if( $result === false ) {
                            $promise->resolve([
                                'code'      => ServerConst::ERR_REDIS_CONNECT_FAILED,
                                'errCode'   => $client->errCode,
                                'errMsg'    => $client->errMsg,
                            ]);
                            return;
                        }
                        if( isset($this->conf['auth']) && !empty($this->conf['auth']) ) {
                            $client->auth($this->conf['auth'], function(\swoole_redis $client, $result) use ($promise){
                                if( $result === false ) {
                                    $this->close();
                                    $promise->resolve([
                                        'code'  => ServerConst::ERR_REDIS_ERROR,
                                        'errCode'   => $client->errCode,
                                        'errMsg'    => $client->errMsg,
                                    ]);
                                    return;
                                }
                                $client->select($this->conf['select'], function(\swoole_redis $client, $result){});
                                $promise->resolve([
                                    'code'  => ServerConst::ERR_SUCCESS
                                ]);
                            });
                        } else {
                            $client->select($this->conf['select'], function(\swoole_redis $client, $result){});
                            $promise->resolve([
                                'code'  => ServerConst::ERR_SUCCESS
                            ]);
                        }
                    });
                break;
            }

            case ServerConst::MODE_SYNC : {
                $this->link = new \Redis();
                try {
                    $result = $this->link->connect($this->conf['host'], $this->conf['port'], $timeout);
                    if( !$result ) {
                        $promise->resolve([
                            'code'      => ServerConst::ERR_REDIS_CONNECT_FAILED,
                            'errCode'   => -1,
                            'errMsg'    => $this->link->getLastError(),
                        ]);
                    }
                    if( isset($this->conf['pwd']) ) {
                        $this->link->auth($this->conf['pwd']);
                    }
                    $this->link->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);
                    $this->link->select($this->conf['select']);
                    $promise->resolve([
                        'code'  => ServerConst::ERR_SUCCESS
                    ]);
                }catch (\RedisException $e) {
                    $promise->resolve([
                        'code'      => ServerConst::ERR_REDIS_CONNECT_FAILED,
                        'errCode'   => $e->getCode(),
                        'errMsg'    => $e->getMessage(),
                    ]);
                }

                break;
            }
        }

        return $promise;
    }

    /**
     * 调用Redis命令
     * @param $name         string      Redis命令
     * @param $arguments    array       Redis命令参数列表
     * @return Promise                  Promise对象
     */
    public function __call($name, $arguments) {
        $promise = new Promise();
        if( $name == 'subscribe' || $name == 'unsubscribe'
            || $name == 'psubscribe' || $name == 'punsubscribe' ) {
            $promise->resolve(null);
            return $promise;
        }

        echo " redis _call mode:$this->mode \r\n";

        switch ($this->mode) {
            case ServerConst::MODE_ASYNC : {
                $this->inPool();
                $index = count($arguments);
                $timeId = swoole_timer_after($this->timeout, function() use ($promise){
                    $this->close();
                    $promise->resolve([
                        'code'  => ServerConst::ERR_REDIS_TIMEOUT
                    ]);
                });
                $arguments[$index] = function (\swoole_redis $client, $result) use ($timeId, $promise){
                    \swoole_timer_clear($timeId);
                    if( $result === false ) {
                        $promise->resolve([
                            'code'      => ServerConst::ERR_REDIS_ERROR,
                            'errCode'   => $client->errCode,
                            'errMsg'    => $client->errMsg,
                        ]);
                        return;
                    }
                    $promise->resolve([
                        'code'  => ServerConst::ERR_SUCCESS,
                        'data'  => $result
                    ]);
                };
                call_user_func_array([$this->db, $name], $arguments);
                break;
            }

            case ServerConst::MODE_SYNC : {
                $result = call_user_func_array([$this->link, $name], $arguments);
                if( $result === false ) {
                    $promise->resolve([
                        'code'      => ServerConst::ERR_REDIS_ERROR
                    ]);
                    break;
                }
                $promise->resolve([
                    'code'  => ServerConst::ERR_SUCCESS,
                    'data'  => $result
                ]);
                break;
            }
        }

        return $promise;
    }


    /**
     * 设置超时时间
     * @param $timeout     int      超时时间，单位ms
     * @return Redis                返回当前对象
     */
    public function setTimeout($timeout) {
        $this->timeout = $timeout;
        return $this;
    }






}