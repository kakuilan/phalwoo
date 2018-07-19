<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/24
 * Time: 23:01
 * Desc: -session的异步redis适配器类,可以读取,但写的时候放入队列给定时器延迟写入[注意使用yield]
 */


namespace Lkk\Phalwoo\Phalcon\Session\Adapter;

use Phalcon\Session\Adapter\Redis as PhalconSession;
use Phalcon\Session\AdapterInterface;
use Phalcon\Session\Exception;
use Phalcon\Events\Manager;
use Lkk\Phalwoo\Phalcon\Session\Adapter;
use Lkk\Phalwoo\Server\DenyUserAgent;
use Lkk\Phalwoo\Server\SwooleServer;
use Lkk\Helpers\CommonHelper;
use Lkk\Phalwoo\Server\ServerConst;
use Lkk\Phalwoo\Server\Component\Client\Redis as RedisClient;

class Redis extends Adapter {

    const CACHE_PREFIX  = '_PHCR';
    const BEGIN_KEY     = '_BEGN'; //保存[初次session访问时间]的键
    const LASTT_KEY     = '_LAST'; //保存[最后session访问时间]的键
    const STATI_KEY     = '_PSVT'; //保存[统计session访问次数]的键

    /**
     * redis连接池名称
     * @var string
     */
    protected $_redis = 'redis_session';

    private $_domain = ''; //cookie域名
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
        if (isset($options['redis']) && !empty($options['redis'])) {
            $this->_redis = $options['redis'];
        }

    }


    /**
     * 获取异步redis连接
     * @return mixed
     */
    public function getAsyncRedis() {
        return SwooleServer::getPoolManager()->get($this->_redis)->pop();
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
            $this->_domain = CommonHelper::getDomain($domain, true);
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
        return SwooleServer::getPoolManager()->has($this->_redis);
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
                yield $this->regenerateId();
                $this->setSessionIdCookie();
                $this->_sessionData[self::STATI_KEY] = 1;
            }
        }

        if(!$isNew) {
            $res = yield $this->read($this->_id);
            if($res['code']==ServerConst::ERR_SUCCESS) {
                $data = unserialize($res['data']);
                $this->_sessionData = $data ? $data : [];
                $ttlRes = yield $this->getAsyncRedis()->ttl($this->getIdKey($this->_id));
                $ttl = intval($ttlRes['data']);
                if($ttl!= -2) $this->_lefttime = $ttl;
            }else{
                $this->setSessionIdCookie();
            }
        }

        //访问次数统计
        $begin = $this->get(self::BEGIN_KEY, 0);
        $stati = $this->get(self::STATI_KEY, 0);

        if(!$begin) $this->set(self::BEGIN_KEY, time());
        $this->set(self::STATI_KEY, ++$stati);
        $this->set(self::LASTT_KEY, time());

        /** @var Manager $eventManager */
        $eventManager = $this->_dependencyInjector->getShared('eventsManager');
        $eventManager->attach('response:beforeSendCookies', function () {
            $this->saveToCache();
        });

        return true;
    }



    /**
     * 异步读取redis里的数据
     * @param string $sessionId
     *
     * @return bool|mixed
     */
    public function read($sessionId=null) {
        $sessionId || $sessionId = $this->getId();
        if(empty($sessionId)) return false;

        $res = yield $this->getAsyncRedis()->get($this->getIdKey($sessionId));
        if($res && is_string($res)) $res = unserialize($res);
        return $res;
    }


    /**
     * 异步将数据写入redis
     * @param $sessionId
     * @param $data
     *
     * @return bool
     */
    public function write($sessionId, $data) {
        $sessionId || $sessionId = $this->getId();
        if(empty($sessionId)) return false;

        if(empty($data)) {
            $res = yield $this->getAsyncRedis()->del($this->getIdKey($sessionId));
        }elseif($this->_lefttime ==0) { //第一次,初始为60s,防止非浏览器压测
            $res = yield $this->getAsyncRedis()->setex($this->getIdKey($sessionId), 60, serialize($data));
        }else{
            if($this->_lefttime < 120) {
                //lefttime计算:第1次,先给5分钟;5分钟内超过10次的,给10分钟;以此类推
                $lefttime = (intval($this->_sessionData[self::STATI_KEY] /10) + 1) * 300;
                if($lefttime> Adapter::SESSION_LIFETIME) $lefttime = Adapter::SESSION_LIFETIME;
                $this->_lefttime = $lefttime;
            }

            $res = yield $this->getAsyncRedis()->setex($this->getIdKey($sessionId), $this->_lefttime, serialize($data));
        }

        return $res;
    }


    /**
     * session写入缓存
     */
    public function saveToCache() {
        $writable = ($this->getWritable() && !empty($this->_sessionData) && ($this->isUpdate() || $this->_lefttime<=120) );
        if(!$writable) {
            return false;
        }

        //lefttime计算:第1次,先给5分钟;5分钟内超过10次的,给10分钟;以此类推
        $stati = $this->get(self::STATI_KEY, 1);
        $lefttime = (intval($stati /10) + 1) * 300;
        if($lefttime> Adapter::SESSION_LIFETIME) $lefttime = Adapter::SESSION_LIFETIME;

        $workData = [
            'key' => $this->getIdKey($this->_id),
            'session' => $this->_sessionData,
            'lefttime' => ($this->_lefttime>120 ? $this->_lefttime : $lefttime),
        ];

        //将session数据放入channel通道队列,然后其他进程再读取写入redis
        $sessionQueue = SwooleServer::getSessionQueue();
        $sessionQueue->push($workData);

        $state = $sessionQueue->stats();
        //echo "session queue len: {$state['queue_num']}\r\n";

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
     * 获取当前用户pv
     * @return mixed
     */
    public function getPv() {
        $stati = $this->get(self::STATI_KEY, 1);
        return $stati;
    }


    /**
     * 获取用户平均每秒访问次数
     * @return float|int
     */
    public function getQps() {
        $begin = $this->get(self::BEGIN_KEY, 0);
        $lastt = $this->get(self::LASTT_KEY, 0);
        $stati = $this->get(self::STATI_KEY, 1);
        $useTime = $lastt - $begin;

        return ($useTime>0) ? ceil($stati/$useTime) : 1;
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
                'key' => $this->getIdKey($this->_id),
                'session' => [],
                'lefttime' => 0,
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
            $redis = $this->getAsyncRedis();
            if(is_object($redis) && ($redis instanceof RedisClient)) {
                yield $redis->del($this->getIdKey());
            }
        }

        $userAgent = $this->_dependencyInjector->getShared('userAgent');
        $id = $userAgent->makeSessionId();
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
     * 获取系统在线人数[同步模式]
     * @return int
     */
    public function getSystemOnlineNum() {
        $like = self::CACHE_PREFIX . $this->_prefix;
        $cmd = 'return table.getn(redis.call("keys", "'.$like.'*"))';
        $prom = SwooleServer::getPoolManager()->get('redis_system')->pop(true)->eval($cmd);
        $promRes = $prom->getResult();
        return intval($promRes['data']);
    }


    /**
     * 获取站点在线人数[同步模式]
     * @param string $siteDomain
     *
     * @return int
     */
    public function getSiteOnlineNum($siteDomain='') {
        if(empty($siteDomain)) $siteDomain = $this->getDomain();
        $like = self::CACHE_PREFIX . $this->_prefix . CommonHelper::getDomain($siteDomain, true) . ':';
        $cmd = 'return table.getn(redis.call("keys", "'.$like.'*"))';
        $prom = SwooleServer::getPoolManager()->get('redis_site')->pop(true)->eval($cmd);
        $promRes = $prom->getResult();
        return intval($promRes['data']);
    }


}