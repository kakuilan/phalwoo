<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/24
 * Time: 23:01
 * Desc: -
 */


namespace Lkk\Phalwoo\Phalcon\Session\Adapter;

use Phalcon\Session\Adapter\Redis as PhalconSession;
use Phalcon\Session\AdapterInterface;
use Phalcon\Session\Exception;
use Phalcon\Events\Manager;
use Lkk\Phalwoo\Phalcon\Session\Adapter;
use Lkk\Phalwoo\Server\DenyUserAgent;
use Lkk\Phalwoo\Server\SwooleServer;

class Redis extends Adapter {

    const CACHE_PREFIX  = '_PHCR';
    const STATI_KEY     = '_PSVT'; //保存[统计session访问次数]的键

    protected $_redis = null;
    private $_domain = '';
    private $_lefttime = 0; //剩余时间ttl,秒
    private $_sessionData = []; //session数据
    private $_writable = false; //是否允许将session写入redis
    private $_isUpdate = false; //是否有更新

    public function __construct(array $options = []) {
        parent::__construct($options);
        if (isset($options['lifetime']) && is_int($options['lifetime'])) {
            $this->_lefttime = $options['lifetime'];
        }
        if (isset($options['cookie']['domain'])) {
            $this->setDomain($options['cookie']['domain']);
        }

        $this->getRedis();
    }


    /**
     * 获取redis客户端
     * @return null|\Redis
     */
    public function getRedis() {
        if(is_null($this->_redis)) {
            $redis = new \Redis();
            $this->_redis = $redis;
        }

        return $this->_redis;
    }


    /**
     * 是否可写
     * @return bool
     */
    public function getWritable() {
        return $this->_writable;
    }


    /**
     * 获取生存期限
     * @return int
     */
    public function getLifetime() {
        return $this->_lifetime ?? 0;
    }


    /**
     * 设置域名
     * @param string $domain
     */
    public function setDomain($domain) {
        if(is_string($domain) && $domain) {
            $this->_domain = $domain;
        }
    }


    /**
     * 获取域名
     * @return mixed
     */
    public function getDomain() {
        return $this->_domain;
    }


    /**
     * 获取SESSION_ID的缓存key
     * @return string
     */
    public function getIdKey($sessionId=null) {
        $sessionId || $sessionId = $this->getId();
        $key = self::CACHE_PREFIX . $this->_prefix . $this->_domain . ':' . $sessionId;
        return $key;
    }


    /**
     * 打开redis连接
     * @return bool
     */
    public function open() {
        $option = $this->getOptions();
        //TODO 使用连接池
        //Warning: Redis::connect(): connect() failed: Cannot assign requested address
        $res = $this->_redis->connect($option['host'], $option['port'], 2.5);
        if(!$res) return false;
        if(isset($option['auth']) && !empty($option['auth'])) {
            $this->_redis->auth($option['auth']);
        }

        $this->_redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
        $this->_redis->select($option['index'] ?? 1);

        return true;
    }


    /**
     *  关闭redis连接
     * @return mixed
     */
    public function close() {
        return $this->_redis->close();
    }



    /**
     * Starts session, optionally using an adapter
     */
    public function start() {
        if (empty($this->_dependencyInjector)) {
            $this->_status = self::SESSION_DISABLED;
            throw new Exception("A dependency injection object is required to access the 'cookies' service");
        }

        if ($this->_started) {
            return true;
        }

        //TODO 检查是否客户端是否可接收cookie

        $this->_started = $this->open();
        if(!$this->_started) {
            $this->_status = self::SESSION_DISABLED;
            return false;
        }

        $isNew = false;
        if (empty($this->_id)) {
            $cookies = $this->_dependencyInjector->getShared('cookies');
            if ($cookies->has($this->_name)) {
                $this->_writable = true;

                $cookie = $cookies->get($this->_name, true);
                $this->_id = $cookie->getValue();
            } else {
                $isNew = true;
                $this->regenerateId();
                $this->setSessionIdCookie();
                $this->_sessionData[self::STATI_KEY] = 1;
            }
        }

        if(!$isNew) {
            $data = $this->read($this->_id);
            if($data) {
                $this->_sessionData = $data;

                $ttl = $this->_redis->ttl($this->getIdKey($this->_id));
                if($ttl!= -2) $this->_lefttime = $ttl;
            }else{
                $this->setSessionIdCookie();
            }
        }

        //访问次数统计
        if(!isset($this->_sessionData[self::STATI_KEY])) $this->_sessionData[self::STATI_KEY] = 0;
        $this->set(self::STATI_KEY, ++$this->_sessionData[self::STATI_KEY]);

        /** @var Manager $eventManager */
        $eventManager = $this->_dependencyInjector->getShared('eventsManager');
        $eventManager->attach('response:beforeSendCookies', function () {
            $this->saveToCache();
        });

        return true;
    }



    /**
     * 读取redis里的数据
     * @param string $sessionId
     *
     * @return bool|mixed
     */
    public function read($sessionId=null) {
        $sessionId || $sessionId = $this->getId();
        if(empty($sessionId)) return false;

        $res = $this->_redis->get($this->getIdKey($sessionId));
        return $res;
    }


    /**
     * 将数据写入redis
     * @param $sessionId
     * @param $data
     *
     * @return bool
     */
    public function write($sessionId, $data) {
        $sessionId || $sessionId = $this->getId();
        if(empty($sessionId)) return false;

        if(empty($data)) {
            $res = $this->_redis->del($this->getIdKey($sessionId));
        }elseif($this->_lefttime ==0) { //第一次,初始为60s,防止非浏览器压测
            $res = $this->_redis->setex($this->getIdKey($sessionId), 60, $data);
        }elseif($this->_lefttime >0 && $this->_lefttime <=120) { //第二次更新为正常
            $res = $this->_redis->setex($this->getIdKey($sessionId), Adapter::SESSION_LIFETIME, $data);
        }else{
            $res = $this->_redis->setex($this->getIdKey($sessionId), $this->_lifetime, $data);
        }

        return $res;
    }


    /**
     * session写入缓存
     */
    public function saveToCache() {
        /*$this->write($this->_id, $this->_sessionData);
        $this->close();*/

        $this->close();
        /*$a = $this->getWritable();
        $b = !empty($this->_sessionData);
        $c = $this->isUpdate();
        $d = $this->_lefttime<=120;*/

        $writable = ($this->getWritable() && !empty($this->_sessionData) && ($this->isUpdate() || $this->_lefttime<=120) );
        //var_dump('$writable', $writable, $a, $b, $c, $d);
        if(!$writable) {
            return false;
        }

        //lefttime计算
        if($this->_sessionData[self::STATI_KEY] <=10) { //第1次,先给5分钟;
            $lefttime = 300;
        }elseif ($this->_sessionData[self::STATI_KEY] <= 20) { //5分钟内超过10次的,给10分钟
            $lefttime = 300;
        }



        $workData = [
            'type' => 'session',
            'data' => [
                'key' => $this->getIdKey($this->_id),
                'session' => $this->_sessionData,
                'lefttime' => ($this->_lefttime>0 ? $this->_lefttime : Adapter::SESSION_LIFETIME),
            ]
        ];
        $inerQueue = SwooleServer::getInerQueue();
        $inerQueue->push($workData);
        $state = $inerQueue->stats();

        echo "队列记录数 {$state['queue_num']}\r\n";
        return true;
    }


    /**
     * 获取所有
     * @return array
     */
    public function getAll() {
        if(empty($this->_uniqueId)) {
            return $this->_sessionData;
        }

        $res = [];
        $prefix = $this->_uniqueId . '#';
        foreach ($this->_sessionData as $key => $item) {
            if(strpos($key, $prefix)===0) {
                $res[$key] = $item;
            }
        }

        return $res;
    }


    /**
     * Gets a session variable from an application context
     *
     * @param string $index
     * @param mixed $defaultValue
     * @return mixed
     */
    public function get($index, $defaultValue = null) {
        if(!empty($this->_uniqueId)) {
            $index = $this->_uniqueId . '#' .$index;
        }

        return $this->_sessionData[$index] ?? $defaultValue;
    }


    /**
     * Sets a session variable in an application context
     *
     * @param string $index
     * @param mixed $value
     */
    public function set($index, $value) {
        if(!empty($this->_uniqueId)) {
            $index = $this->_uniqueId . '#' .$index;
        }

        if(!isset($this->_sessionData[$index]) || $value!==$this->_sessionData[$index]) $this->_isUpdate = true;

        $this->_sessionData[$index] = $value;
    }


    /**
     * Check whether a session variable is set in an application context
     *
     * @param string $index
     * @return bool
     */
    public function has($index) {
        if(!empty($this->_uniqueId)) {
            $index = $this->_uniqueId . '#' .$index;
        }

        return isset($this->_sessionData[$index]);
    }

    /**
     * Removes a session variable from an application context
     *
     * @param string $index
     */
    public function remove($index) {
        if(!empty($this->_uniqueId)) {
            $index = $this->_uniqueId . '#' .$index;
        }

        unset($this->_sessionData[$index]);
    }


    /**
     * Destroys the active session
     *
     * @param bool $removeData
     * @return bool
     */
    public function destroy($removeData = false) {
        $this->_sessionData = [];
        $this->_started = false;
        if($removeData) {
            $workData = [
                'type' => 'session',
                'data' => [
                    'key' => $this->getIdKey($this->_id),
                    'session' => [],
                    'lefttime' => 0,
                ]
            ];
            $inerQueue = SwooleServer::getInerQueue();
            $inerQueue->push($workData);
        }

        return true;
    }

    /**
     * Regenerate session's id
     *
     * @param bool $deleteOldSession
     * @return AdapterInterface
     */
    public function regenerateId($deleteOldSession = true) {
        if (empty($this->_dependencyInjector)) {
            throw new Exception("A dependency injection object is required to access the 'request,cookies' service");
        }

        if ($deleteOldSession === true) {
            $this->_redis->del($this->getIdKey());
        }

        $denAgent = $this->_dependencyInjector->getShared('denAgent');
        $id = $denAgent->makeSessionId();
        $this->setId($id);

        return $this;
    }


    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function gc() {
        //TODO
        //session.gc_maxlifetime
    }


    /**
     * 设置sessionId的cookie
     */
    public function setSessionIdCookie() {
        $cookieConf = $this->getOption('cookie', null);
        $cookieLifetime = $cookieConf['lifetime'] ?? 1800;
        $cookiePath = $cookieConf['path'] ?? '/';
        $cookieDomain = $cookieConf['domain'] ?? '*';
        $cookieSecure = false;
        $cookieHttponly = true;
        $cookieEncrypt = true;

        $cookies = $this->_dependencyInjector->getShared('cookies');
        $cookies->set($this->_name, $this->_id, $cookieLifetime, $cookiePath, $cookieSecure, $cookieDomain, $cookieHttponly, $cookieEncrypt);
    }


    /**
     * 数据是否有更新
     * @return int
     */
    public function isUpdate(){
        return $this->_isUpdate;
    }


    /**
     * 获取系统在线人数
     * @return int
     */
    public function getSystemOnlineNum() {
        $like = self::CACHE_PREFIX . $this->_prefix;
        $count = $this->_redis->eval('return table.getn(redis.call("keys", "'.$like.'*"))');
        return intval($count);
    }


    /**
     * 获取站点在线人数
     * @param string $siteDomain
     *
     * @return int
     */
    public function getSiteOnlineNum($siteDomain='') {
        if(empty($siteDomain)) $siteDomain = $this->getDomain();
        $like = self::CACHE_PREFIX . $this->_prefix . $siteDomain . ':';
        $count = $this->_redis->eval('return table.getn(redis.call("keys", "'.$like.'*"))');
        return intval($count);
    }


}