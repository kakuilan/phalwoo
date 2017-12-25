<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/12/25
 * Time: 14:46
 * Desc:
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
    private $agentFpName = '_afp'; //客户端(浏览器)指纹参数名称
    private $agentFpValu = ''; //客户端(浏览器)指纹参数值
    private $tokenName = 'token'; //token参数名称
    private $tokenValu = ''; //token值
    private $tokenFunc = null; //token验证函数
    private $sessionHasFp = false; //sessionId是否包含指纹

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

    public function setTokenName(string $str) {
        if(!empty($str)) $this->tokenName = $str;
    }


    public function getTokenName() {
        return $this->tokenName;
    }


    public function setTokenFunc(callable $func) {
        $this->tokenFunc = $func;
    }


    public function getTokenValu() {
        return $this->tokenValu;
    }


    /**
     * @param \swoole_http_request $request
     */
    public function setSwRequest(\swoole_http_request $request) {
        $this->swRequest = $request;

        $fpName = $this->getAgentFpName();
        $tkName = $this->getTokenName();

        $this->agentFpValu = $this->request->get[$fpName] ?? ($this->request->post[$fpName] ?? ($this->request->cookie[$fpName] ?? ''));
        $this->tokenValu = $this->request->get[$tkName] ?? ($this->request->post[$tkName] ?? ($this->request->cookie[$tkName] ?? ''));

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
     * @return bool
     */
    public function isSessionHasFp() {
        $res = false;
        if(isset($this->request->cookie)) { //已有的session
            $lastSessionId = $this->getSessionIdFromCookie();
            $res = boolval(substr($lastSessionId, 22, 1));
        }else{ //新的
            $this->getAgentUuid();
            $res = $this->sessionHasFp;
        }

        return $res;
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
        }else{
            $res = $this->swRequest->server['remote_addr'];
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
        if(stripos($ip,'127.')===0||stripos($ip,'10.')===0||stripos($ip,'192.')) {
            return true;
        }

        if(!$this->checkAgent()) return false;
        if(!$this->checkIp()) return false;

        if(isset($this->swRequest->cookie)) {
            if(!$this->checkCookie()) return false;
        }

        if(!empty($this->tokenValu) && is_callable($this->tokenFunc)) {
            if(!call_user_func_array($this->tokenFunc, [$this->tokenValu])) return false;
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
                $crypt = $this->_dependencyInjector->getShared('crypt');
                $decryptedValue = $crypt->decryptBase64($sessionValue);
                $uuid = $this->getAgentUuid();
                if(substr($decryptedValue, 23) !==$uuid) {
                    $this->setError('cookies的sessionId错误');
                    return false;
                }
            }

        }

        return true;
    }


    /**
     * 获取agen的uuid
     * @return int
     */
    public function getAgentUuid() {
        if(is_null($this->agentUuid)) {
            $arr = [];

            $arr['agent-fingerprint'] = $this->agentFpValu;
            $arr['host'] = $this->request->header['host'] ?? '';
            $arr['user-agent'] = $this->request->header['user-agent'] ?? '';
            $arr['accept-language'] = $this->request->header['accept-language'] ?? '';
            $arr['accept-encoding'] = $this->request->header['accept-encoding'] ?? '';
            $arr['dnt'] = $this->request->header['dnt'] ?? '';
            $arr['connection'] = $this->request->header['connection'] ?? '';
            ksort($arr);

            $this->sessionHasFp = !empty($this->agentFpValu);
            $this->agentUuid = EncryptHelper::murmurhash3_int(json_encode($arr), 13, true);
        }

        return $this->agentUuid;
    }


    /**
     * 获取不包含指纹的uuid
     * @return int
     */
    public function getAgentUuidNofp() {
        $arr = [];

        $arr['agent-fingerprint'] = $this->agentFpValu;
        $arr['host'] = $this->request->header['host'] ?? '';
        $arr['user-agent'] = $this->request->header['user-agent'] ?? '';
        $arr['accept-language'] = $this->request->header['accept-language'] ?? '';
        $arr['accept-encoding'] = $this->request->header['accept-encoding'] ?? '';
        $arr['dnt'] = $this->request->header['dnt'] ?? '';
        $arr['connection'] = $this->request->header['connection'] ?? '';
        ksort($arr);

        $res = EncryptHelper::murmurhash3_int(json_encode($arr), 13, true);
        return $res;
    }



    /**
     * 生产sessionId
     * @return string
     */
    public function makeSessionId() {
        if(is_null($this->sessionId)) {
            $uuid = $this->getAgentUuid();
            $ip = $this->swRequest->header['x_forwarded_for'] ??
                ($this->swRequest->header['client_ip'] ?? ($this->swRequest->header['remote_addr'] ?? '127.0.0.1'));
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