<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/9/16
 * Time: 10:43
 * Desc: -
 */


namespace Lkk\Phalwoo\Phalcon\Cache\Backend;

use Phalcon\Cache\FrontendInterface;
use Phalcon\Cache\BackendInterface;
use Lkk\Phalwoo\Phalcon\Cache\Backend;
use Lkk\Phalwoo\Phalcon\Cache\Exception;
use Lkk\Phalwoo\Server\SwooleServer;
use Lkk\Phalwoo\Server\ServerConst;

class Redis extends Backend {


    /**
     * redis连接池名称
     * @var string
     */
    protected $_redis = 'redis_system';

    /**
     * Default to false for backwards compatibility
     *
     * @var boolean
     */
    private $_useSafeKey = false;


    /**
     * Phalcon\Cache\Backend\Redis constructor
     *
     * @param	FrontendInterface $frontend
     * @param	array $ptions
     * @param mixed $options
     */
    public function __construct(FrontendInterface $frontend, $options = null) {
        if (isset($options['redis'])) {
            if (!is_string($options['redis'])) {
                throw new Exception("redis option should be a string.");
            }

            $this->_redis = $options['redis'];
        }


        if (isset($options['safekey'])) {
            $safekey = $options['safekey'];
            if (!is_bool($safekey)) {
                throw new Exception("safekey option should be a boolean.");
            }

            $this->_useSafeKey = $safekey;
        }

        // added to avoid having unsafe filesystem characters in the prefix
        if (isset($options['prefix'])) {
            $prefix = $options['prefix'];
            if ($this->_useSafeKey && preg_match('/[^a-zA-Z0-9_.-]+/', $prefix)) {
                throw new Exception("FileCache prefix should only use alphanumeric characters.");
            }
            $this->_prefix = $prefix;
        }

        parent::__construct($frontend, $options);
    }


    /**
     * Create internal connection to redis
     */
    public function _connect() {
        return true;
    }


    /**
     * Returns a cached content
     *
     * @param string $keyName
     * @param int $lifetime
     * @return mixed|null
     */
    public function get($keyName, $lifetime = null) {
        $res = null;

        $prefixedkey = $this->_prefix . $this->getkey($keyName);
        $ttlRes = yield $this->getRedis()->ttl($prefixedkey);
        $ttl = intval($ttlRes['data']);
        if($ttl==-2) return $res;

        $req = yield $this->getRedis()->get($prefixedkey);
        if($req['code'] != ServerConst::ERR_SUCCESS) {
            return false;
        }

        $cachedContent = $req['data'];
        unset($ttlRes, $req);

        $this->_lastKey = $prefixedkey;

        if (is_numeric($cachedContent)) {
            return $cachedContent;
        } else {
            /**
             * Use the frontend to process the content of the cache
             */
            $res = $this->_frontend->afterRetrieve($cachedContent);
        }

        return $res;
    }


    /**
     * Stores cached content into the file backend and stops the frontend
     *
     * <code>
     * $cache->save("my-key", $data);
     *
     * // Save data termlessly
     * $cache->save("my-key", $data, -1);
     * </code>
     *
     * @param int|string $keyName
     * @param string $content
     * @param int $lifetime
     * @param boolean $stopBuffer
     * @return bool
     */
    public function save($keyName = null, $content = null, $lifetime = null, $stopBuffer = true) {
        if (!$keyName) {
            $lastKey = $this->_lastKey;
        } else {
            $lastKey = $this->_prefix . $this->getkey($keyName);
        }

        if (!$lastKey) {
            throw new Exception('The cache must be started first.');
        }

        if (!$content) {
            $cachedContent = $this->_frontend->getContent();
        } else {
            $cachedContent = $content;
        }

        if(!is_numeric($lifetime) || $lifetime==0) {
            if (!$this->_lastLifetime) {
                $lifetime = $this->_frontend->getLifetime();
            } else {
                $lifetime = $this->_lastLifetime;
            }
        }
        $lifetime = intval($lifetime);

        $preparedContent = is_numeric($cachedContent) ? $cachedContent : $this->_frontend->beforeStore($cachedContent);
        $status = yield $this->getRedis()->setex($lastKey, $lifetime, $preparedContent);

        if($status['code']!=ServerConst::ERR_SUCCESS) return false;

        if ($stopBuffer === true) {
            $this->_frontend->stop();
        }

        $isBuffering = $this->_frontend->isBuffering();
        if ($isBuffering === true) {
            echo $cachedContent;
        }

        $this->_started = false;
        $this->_lastKey = $lastKey;
        $this->_lastLifetime = $lifetime;

        unset($content, $cachedContent, $preparedContent);

        return true;
    }


    /**
     * Deletes a value from the cache by its key
     *
     * @param int|string $keyName
     * @return bool
     */
    public function delete($keyName) {
        $prefixedkey = $this->_prefix . $this->getkey($keyName);
        $res = yield $this->getRedis()->del($prefixedkey);
        return boolval($res['code']==ServerConst::ERR_SUCCESS);
    }


    /**
     * Query the existing cached keys.
     *
     * <code>
     * $cache->save("users-ids", [1, 2, 3]);
     * $cache->save("projects-ids", [4, 5, 6]);
     *
     * var_dump($cache->queryKeys("users")); // ["users-ids"]
     * </code>
     *
     * @param string $prefix
     * @return array
     */
    public function queryKeys($prefix = null) {
        $key = $this->_prefix . $prefix;
        $search = "{$key}*";
        $res = yield $this->getRedis()->keys($search);
        return ($res['code']==ServerConst::ERR_SUCCESS) ? $res['data'] : [];
    }


    /**
     * Checks if cache exists and it isn't expired
     *
     * @param string $keyName
     * @param int $lifetime
     * @return bool
     */
    public function exists($keyName = null, $lifetime = null) {
        $prefixedkey = $this->_prefix . $this->getkey($keyName);

        $ttlRes = yield $this->getRedis()->ttl($prefixedkey);
        $ttl = intval($ttlRes['data']);
        return !($ttl==-2);
    }


    /**
     * Increment of given $keyName by $value
     *
     * @param string $keyName
     * @param int $value
     * @return int
     */
    public function increment($keyName = null, $value = 1) {
        $value = ($value>1) ? intval($value) : 1;
        $prefixedkey = $this->_prefix . $this->getkey($keyName);
        $res = yield $this->getRedis()->incrBy($prefixedkey, $value);
        return ($res['code']==ServerConst::ERR_SUCCESS) ? intval($res['data']) : 0;
    }


    /**
     * Decrement of $keyName by given $value
     *
     * @param string $keyName
     * @param int $value
     * @return int
     */
    public function decrement($keyName = null, $value = 1) {
        $value = ($value>1) ? intval($value) : 1;
        $prefixedkey = $this->_prefix . $this->getkey($keyName);
        $res = yield $this->getRedis()->decrBy($prefixedkey, $value);
        return ($res['code']==ServerConst::ERR_SUCCESS) ? intval($res['data']) : 0;
    }


    /**
     * Immediately invalidates all existing items.
     *
     * @return bool
     */
    public function flush() {
        $res = false;
        if($this->_prefix) {
            $keys = yield $this->queryKeys($this->_prefix);
            if($keys) {
                $res = true;
                $slices = array_chunk($keys, 10);
                foreach ($slices as $slice) {
                    $delRes = yield $this->getRedis()->delete($slice);
                }
            }
        }else{
            $ret = yield $this->getRedis()->flushDB();
            $res = ($ret['code']==ServerConst::ERR_SUCCESS);
        }

        return $res;
    }


    /**
     * Return a redis safe identifier for a given key
     *
     * @param string $key
     * @return string
     */
    public function getkey($key) {
        if ($this->_useSafeKey === true) {
            return md5($key);
        }

        return $key;
    }



    /**
     * Set whether to use the safekey or not
     *
     * @return $this
     */
    public function useSafeKey($useSafeKey) {
        if (!is_bool($useSafeKey)) {
            throw new Exception('The useSafeKey must be a boolean');
        }

        $this->_useSafeKey = $useSafeKey;

        return $this;
    }


    /**
     * 获取redis连接
     * @return mixed
     */
    private function getRedis() {
        return SwooleServer::getPoolManager()->get($this->_redis)->pop();
    }



}