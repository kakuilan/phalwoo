<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/12/25
 * Time: 14:46
 * Desc: user-agent服务类
 */

namespace Lkk\Phalwoo\Phalcon\Http;

use Lkk\Helpers\ArrayHelper;
use Lkk\Helpers\EncryptHelper;
use Lkk\LkkService;
use Lkk\Phalwoo\Phalcon\Session\Adapter as SessionAdapter;
use Phalcon\DiInterface;
use Phalcon\Di\InjectionAwareInterface;

class UserAgent extends LkkService implements InjectionAwareInterface {

    protected $_dependencyInjector;
    private $swRequest; //swoole的request对象
    private $agentUuid;
    private $sessionId;
    private $allowBench = false; //是否允许ab压测
    private $agentFpName = 'uafp'; //客户端(浏览器)指纹参数名称
    private $agentFpValue = ''; //客户端(浏览器)指纹参数值
    private $tokenName = 'token'; //token参数名称
    private $tokenValue = ''; //token值
    private $tokenFunc = null; //token验证函数
    private $sessionHasFp = false; //当前sessionId是否包含指纹
    private $sidChange = false; //sessionId是否已变化

    //禁止的客户端关键词
    public static $denyAgentKeywords = ['crawler','spider','guzzle'];


    public function __construct(array $vars = []) {
        parent::__construct($vars);
    }


    /**
     * Sets the dependency injector
     *
     * @param \Phalcon\DiInterface $dependencyInjector
     */
    public function setDI(\Phalcon\DiInterface $dependencyInjector) {
        $this->_dependencyInjector = $dependencyInjector;
    }


    /**
     * Returns the internal dependency injector
     *
     * @return \Phalcon\DiInterface
     */
    public function getDI() {
        return $this->_dependencyInjector;
    }

    /**
     * 设置客户端指纹参数名称
     * @param string $str
     */
    public function setAgentFpName(string $str) {
        if(!empty($str)) $this->agentFpName = $str;
    }


    /**
     * 获取客户端指纹参数名称
     * @return string
     */
    public function getAgentFpName() {
        return $this->agentFpName;
    }


    /**
     * 获取客户端指纹参数值
     * @return string
     */
    public function getAgentFpValue() {
        return $this->agentFpValue;
    }


    /**
     * 设置token参数名
     * @param string $str
     */
    public function setTokenName(string $str) {
        if(!empty($str)) $this->tokenName = $str;
    }


    /**
     * 获取token参数名
     * @return string
     */
    public function getTokenName() {
        return $this->tokenName;
    }


    /**
     * 设置token校验函数
     * @param callable $func
     */
    public function setTokenFunc(callable $func) {
        if(is_callable($func)) $this->tokenFunc = $func;
    }


    /**
     * 获取token值
     * @return string
     */
    public function getTokenValue() {
        return $this->tokenValue;
    }


    /**
     * 设置swoole请求对象
     * 须先设置setAgentFpName,setTokenName,setTokenFunc
     * @param \swoole_http_request $request
     */
    public function setSwRequest(\swoole_http_request $request) {
        $this->swRequest = $request;
        $fpName = $this->getAgentFpName();
        $tkName = $this->getTokenName();

        $this->agentFpValue = $this->swRequest->get[$fpName] ?? ($this->swRequest->post[$fpName] ?? ($this->swRequest->cookie[$fpName] ?? ''));
        $this->tokenValue = $this->swRequest->get[$tkName] ?? ($this->swRequest->post[$tkName] ?? ($this->swRequest->cookie[$tkName] ?? ''));
    }


    /**
     * @return mixed
     */
    public function getSwRequest() {
        return $this->swRequest;
    }

    /**
     * 设置是否允许压测
     * @param bool $status
     */
    public function setAllowBench($status=false) {
        $this->allowBench = $status;
    }


    /**
     * 判断sessionID中是否包含指纹
     * @param string $sessionId
     * @return bool
     */
    public function isSessionHasFp($sessionId='') {
        if(!empty($sessionId)) { //传入值去判断
            $res = boolval(substr($sessionId, 22, 1));
        }else {
            if(isset($this->request->cookie)) { //已有的session
                $lastSessionId = $this->getSessionIdFromCookie();
                $res = boolval(substr($lastSessionId, 22, 1));
            }else{ //新的
                $this->getAgentUuidReal();
                $res = $this->sessionHasFp;
            }
        }

        return $res;
    }


    /**
     * sessionId是否有改变
     * @return bool
     */
    public function isSessionIdChange() {
        return $this->sidChange;
    }


    /**
     * @return string
     */
    public function getIp() {
        $res = '';
        if(isset($this->swRequest->header['x-real-ip'])) {
            $res = $this->swRequest->header['x-real-ip'];
        }elseif (isset($this->swRequest->header['x-forwarded-for'])) {
            $res = $this->swRequest->header['x-forwarded-for'];
        }elseif (isset($this->swRequest->header['client-ip'])) {
            $res = $this->swRequest->header['client-ip'];
        }elseif (isset($this->swRequest->server['remote_addr'])) {
            $res = $this->swRequest->server['remote_addr'];
        }else{
            $res = '127.0.0.1';
        }

        return $res;
    }


    /**
     * 获取要拒绝的agent
     * @return array
     */
    public function getDenyAgentKeywords() {
        $res = self::$denyAgentKeywords;
        if(!$this->allowBench) array_push($res, 'bench');

        return $res;
    }


    /**
     * 从cookie中获取sessionID
     * @return mixed
     */
    public function getSessionIdFromCookie() {
        static $res;
        if(is_null($res) && isset($this->swRequest->cookie)) {
            foreach ($this->swRequest->cookie as $key=>$item) {
                if(strpos($key, SessionAdapter::SESSION_NAME) !==false) {
                    $sessionValue = $item;
                    $crypt = $this->_dependencyInjector->getShared('crypt');
                    $res = $crypt->decryptBase64($sessionValue);
                    break;
                }
            }
        }

        return $res;
    }


    /**
     * 执行客户端验证
     * @return bool
     */
    public function validate() {
        $ip = $this->getIp();

        //如果是内网,通过
        if(stripos($ip,'127.')===0||stripos($ip,'10.')===0||stripos($ip,'192.')===0) {
            return true;
        }

        if(!$this->checkAgent()) return false;
        if(!$this->checkIp()) return false;

        if(isset($this->swRequest->cookie)) {
            if(!$this->checkCookie()) return false;
        }

        if(!empty($this->tokenValue) && is_callable($this->tokenFunc)) {
            if(!call_user_func_array($this->tokenFunc, [$this->tokenValue])) return false;
        }

        return true;
    }



    /**
     * 检查客户端头信息
     * @return bool
     */
    public function checkAgent() {
        $agenInfo = $this->swRequest->header['user-agent'] ?? '';
        if(empty($agenInfo)) {
            $this->setError('user-agent为空');
            return false;
        }elseif (ArrayHelper::dstrpos($agenInfo, $this->getDenyAgentKeywords())) {
            $this->setError('user-agent被禁止');
            return false;
        }

        return true;
    }


    /**
     * 检查IP
     * @return bool
     */
    public function checkIp() {
        //TODO IP黑名单
        return true;
    }


    /**
     * 检查cookie(sessionId)
     * @return bool
     */
    public function checkCookie() {
        if(isset($this->swRequest->cookie)) {
            $sessionValue = $this->getSessionIdFromCookie();
            $hasSessionId = !empty($sessionValue);

            if(!$hasSessionId) {
                $this->setError('cookies没有sessionId');
                return false;
            }else{
                $lastHasFp = $this->isSessionHasFp($sessionValue);
                $uuid = $lastHasFp ? $this->getAgentUuidReal() : $this->getAgentUuidNofp();

                //检查sessionId是否有变化
                $this->sidChange = boolval($this->sessionHasFp === $lastHasFp);

                if(substr($sessionValue, 23) !==$uuid) {
                    $this->setError('cookies的sessionId错误');
                    return false;
                }

                //检查sessionId是否有变化
                $this->sidChange = boolval($this->sessionHasFp === $lastHasFp);
            }

        }

        return true;
    }


    /**
     * 获取包含指纹的uuid
     * @return int
     */
    public function getAgentUuidReal() {
        return $this->_getAgentUuid(true);
    }


    /**
     * 获取不包含指纹的uuid
     * @return int
     */
    public function getAgentUuidNofp() {
        return $this->_getAgentUuid(false);
    }


    /**
     * 获取简单的uuid(使用md5快速)
     * @param bool $hasFp
     * @return mixed
     */
    public function getAgentUuidSimp($hasFp=true) {
        $flag = '2-' . intval($hasFp);

        if(is_null($this->agentUuid) || !isset($this->agentUuid[$flag])) {
            $arr = [];

            $arr['agent-fingerprint'] = $hasFp ? $this->agentFpValue : '';
            $arr['host'] = $this->request->header['host'] ?? '';
            $arr['user-agent'] = $this->request->header['user-agent'] ?? '';
            $arr['accept-language'] = $this->request->header['accept-language'] ?? '';
            $arr['accept-encoding'] = $this->request->header['accept-encoding'] ?? '';
            $arr['dnt'] = $this->request->header['dnt'] ?? '';
            $arr['connection'] = $this->request->header['connection'] ?? '';
            ksort($arr);

            $this->agentUuid[$flag] = substr(md5(json_encode($arr)),8,8);
        }

        return $this->agentUuid[$flag];
    }



    /**
     * 获取agen的uuid
     * @param bool $hasFp
     * @return mixed
     */
    private function _getAgentUuid($hasFp=true) {
        if($hasFp && empty($this->agentFpValue)) $hasFp = false;
        $flag = '1-' . intval($hasFp);
        $this->sessionHasFp = !empty($this->agentFpValue);

        if(is_null($this->agentUuid) || !isset($this->agentUuid[$flag])) {
            $arr = [];

            $arr['agent-fingerprint'] = $hasFp ? $this->agentFpValue : '';
            $arr['host'] = $this->request->header['host'] ?? '';
            $arr['user-agent'] = $this->request->header['user-agent'] ?? '';
            $arr['accept-language'] = $this->request->header['accept-language'] ?? '';
            $arr['accept-encoding'] = $this->request->header['accept-encoding'] ?? '';
            $arr['dnt'] = $this->request->header['dnt'] ?? '';
            $arr['connection'] = $this->request->header['connection'] ?? '';
            ksort($arr);

            $this->agentUuid[$flag] = EncryptHelper::murmurhash3_int(json_encode($arr), 13, true);
        }

        return $this->agentUuid[$flag];
    }


    /**
     * 生产sessionId
     * @return string
     */
    public function makeSessionId() {
        if(is_null($this->sessionId)) {
            $uuid = $this->getAgentUuidReal();
            $ip = $this->getIp();
            $rand = md5(uniqid($ip.microtime(true), true));
            $rand = substr($rand,8,16);
            $time = substr(time(), -6);

            //共34位,前6位是时间,中间16位随机,往后1位指纹标识,后面11位数字是uuid
            $fpFlag = intval($this->sessionHasFp);
            $this->sessionId = "{$time}{$rand}{$fpFlag}{$uuid}";
        }

        return $this->sessionId;
    }




}