<?php
/**
 * Created by PhpStorm.
 * User: kakuilan@163.com
 * Date: 17-9-12
 * Time: 下午1:51
 * Desc: -MySQL连接对象的封装
 */


namespace Lkk\Phalwoo\Server\Component\Client;

use Lkk\Phalwoo\Server\SwooleServer;
use Lkk\Phalwoo\Server\ServerConst;
use Lkk\Phalwoo\Server\Component\Pool\Adapter as PoolAdapter;
use Lkk\Phalwoo\Server\Concurrent\Promise;

class Mysql {

    /**
     * 连接ID
     * @var int
     */
    public $id;

    /**
     * 配置选项
     * @var array
     */
    private $conf;

    /**
     * swoole_mysql连接实例
     * @var \swoole_mysql
     */
    private $db;

    /**
     * 同步模式的mysqli实例
     * @var \mysqli
     */
    private $link;

    /**
     * 所属连接池
     * @var PoolAdapter
     */
    private $pool;

    /**
     * 模式
     * @var int
     */
    private $mode;

    /**
     * 是否记录请求日志
     * @var bool
     */
    private $open_log = false;

    /**
     * 是否已经关闭连接
     * @var bool
     */
    private $close = true;

    /**
     * MySQL constructor.
     * @param $config       array       配置选项
     * @param $mode         int         模式(<b>ServerConst</b>中的<b>MODE</b>常量)
     */
    public function __construct($config, $mode = ServerConst::MODE_ASYNC) {
        $this->conf   = $config;
        $this->mode     = $mode;
        $this->open_log = $config['open_log'] ?? false;
    }

    /**
     * 设置所属的连接池
     * @param $pool     PoolAdapter    连接池对象
     */
    public function addPool($pool) {
        $this->pool = $pool;
    }

    /**
     * 建立数据库连接
     * @param $id           int     连接ID
     * @param $timeout      int     超时时间, 单位ms
     * @return Promise              Promise对象
     */
    public function connect($id, $timeout=3000) {
        $this->id = $id;
        $promise = new Promise();

        switch ($this->mode) {
            case ServerConst::MODE_ASYNC : {
                $this->db = new \swoole_mysql();
                $this->db->on('Close', function($db){
                    //Log::INFO('MySQL', "Close connection {$this->id}" );
                    $this->close = true;
                    unset($this->db);
                    $this->inPool(true);
                });
                $timeId = swoole_timer_after($timeout, function() use ($promise){
                    $this->close();
                    $promise->reject(ServerConst::ERR_MYSQL_TIMEOUT);
                });
                $this->db->connect($this->conf, function($db, $r) use ($promise,$timeId) {
                    swoole_timer_clear($timeId);
                    if ($r === false) {
                        //Log::ERROR('MySQL' , sprintf("Connect MySQL Failed [%d]: %s", $db->connect_errno, $db->connect_error));
                        $promise->reject(ServerConst::ERR_MYSQL_CONNECT_FAILED);
                        return;
                    }
                    $this->close = false;
                    $promise->resolve(ServerConst::ERR_SUCCESS);
                });
                break;
            }
            
            case ServerConst::MODE_SYNC : {
                $dbHost = $this->conf['host'];
                $dbUser = $this->conf['user'];
                $dbPwd  = $this->conf['password'];
                $dbName = $this->conf['database'];

                $this->link = new \mysqli($dbHost, $dbUser, $dbPwd, $dbName);

                if ($this->link->connect_error) {
                    //Log::ERROR('MySQL' , sprintf("Connect MySQL Failed [%d]: %s", $this->link->connect_errno, $this->link->connect_error));
                    $promise->reject(ServerConst::ERR_MYSQL_CONNECT_FAILED);
                    break;
                }
                $this->link->set_charset($this->conf['charset'] ?? 'utf8');
                $this->close = false;
                $promise->resolve(ServerConst::ERR_SUCCESS);
                break;
            }
        }

        return $promise;
    }


    /**
     * 关闭数据库连接
     */
    public function close() {
        $this->close = true;
        switch ($this->mode) {
            case ServerConst::MODE_ASYNC : {
                $this->db->close();
                unset($this->db);
                $this->inPool(true);
                break;
            }
            case ServerConst::MODE_SYNC : {
                $this->link->close();
                break;
            }
        }
    }


    private function inPool($close = false) {
        if( !empty($this->pool) ) {
            $this->pool->push($this, $close);
        }
    }


    /**
     * 执行SQL请求
     * @param $sql          string      SQL语句
     * @param $get_one      bool        查询1条记录
     * @param $timeout      int         超时时间, 单位ms
     * @return Promise                  Promise对象
     */
    public function execute($sql, $get_one, $timeout=3000) {
        $promise = new Promise();
        switch ($this->mode) {
            case ServerConst::MODE_ASYNC : {
                $timeId = swoole_timer_after($timeout, function() use ($promise, $sql){
                    //Log::ERROR('MySQL', [$sql, -1, "timeout"]);
                    $this->inPool();
                    $promise->resolve([
                        'code' => ServerConst::ERR_MYSQL_TIMEOUT,
                    ]);
                });
                $time = microtime(true) * 1000;
                $this->db->query($sql, function($db, $result) use ($sql, $promise, $timeId, $get_one, $time){
                    $this->inPool();
                    swoole_timer_clear($timeId);
                    if($result === false) {
                        //Log::ERROR('MySQL', [$sql, $db->errno, $db->error]);
                        $promise->resolve([
                            'code'  => ServerConst::ERR_MYSQL_QUERY_FAILED,
                            'errno' => $db->errno
                        ]);
                    } else {
                        if($this->open_log) {
                            $time = microtime(true) * 1000 - $time;
                            //Log::INFO('MySQL', [$time, $sql]);
                        }
                        if($result === true) {
                            $promise->resolve([
                                'code'          => ServerConst::ERR_SUCCESS,
                                'affected_rows' => $db->affected_rows,
                                'insert_id'     => $db->insert_id
                            ]);
                        } else {
                            $promise->resolve([
                                'code'  => ServerConst::ERR_SUCCESS,
                                'data'  => empty($result) ? [] : ($get_one ? $result[0] :$result)
                            ]);
                        }
                    }
                });
                break;
            }
            case ServerConst::MODE_SYNC : {
                $time = microtime(true) * 1000;
                $result = $this->link->query($sql);
                if($this->link->errno == 2006) {
                    $this->close();
                    $this->connect($this->id);
                    $time = microtime(true) * 1000;
                    $result = $this->link->query($sql);
                }
                if($result === false) {
                    //Log::ERROR('MySQL', [$sql, $this->link->errno, $this->link->error]);
                    $promise->resolve([
                        'code'  => ServerConst::ERR_MYSQL_QUERY_FAILED,
                        'errno' => $this->link->errno
                    ]);
                } else {
                    if($this->open_log) {
                        $time = microtime(true) * 1000 - $time;
                        //Log::INFO('MySQL', [$time, $sql]);
                    }
                    if($result === true) {
                        $promise->resolve([
                            'code'          => ServerConst::ERR_SUCCESS,
                            'affected_rows' => $this->link->affected_rows,
                            'insert_id'     => $this->link->insert_id
                        ]);
                    } else {
                        $result_arr = $result->fetch_all(\MYSQLI_ASSOC);
                        $promise->resolve([
                            'code'  => ServerConst::ERR_SUCCESS,
                            'data'  => empty($result_arr) ? [] : ($get_one ? $result_arr[0] : $result_arr)
                        ]);
                    }
                }
                break;
            }
        }

        return $promise;
    }


    public function escape($value) {
        switch ($this->mode) {
            case ServerConst::MODE_ASYNC: {
                return $this->db->escape($value);
            }
            case ServerConst::MODE_SYNC: {
                return $this->link->escape_string($value);
            }
            default:
                return $value;
        }
    }


    /**
     * @return boolean
     */
    public function isClose() {
        return $this->close;
    }


}