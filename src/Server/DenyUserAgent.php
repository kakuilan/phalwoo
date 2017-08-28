<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/27
 * Time: 11:25
 * Desc: -禁止客户端的检查服务类
 */


namespace Lkk\Phalwoo\Server;

use Lkk\LkkService;
use Lkk\Helpers\ArrayHelper;
use Lkk\Helpers\EncryptHelper;
use Phalcon\DiInterface;
use Phalcon\Di\InjectionAwareInterface;
use Lkk\Phalwoo\Phalcon\Session\Adapter as SessionAdapter;

class DenyUserAgent extends LkkService implements InjectionAwareInterface {

    protected $_dependencyInjector;
    private $request; //swoole的request对象
    private $agentUuid;
    private $sessionId;
    private $allowBench = false; //是否允许ab压测

    //禁止的客户端关键词
    public static $denyAgentKeywords = ['crawler','spider'];


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



    public function setRequest(\swoole_http_request $request) {
        $this->request = $request;
    }


    public function getRequest() {
        return $this->request;
    }


    public function setAllowBench($status=false) {
        $this->allowBench = $status;
    }


    /**
     * 检查全部
     * @return bool
     */
    public function checkAll() {
        if(!$this->checkAgent()) return false;
        if(!$this->checkIp()) return false;
        if(!$this->checkCookie()) return false;

        return true;
    }



    public function getDenyAgentKeywords() {
        $res = self::$denyAgentKeywords;
        if(!$this->allowBench) array_push($res, 'bench');

        return $res;
    }


    /**
     * 检查客户端
     * @return bool
     */
    public function checkAgent() {
        $agenInfo = $this->request->header['user-agent'] ?? '';
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
        //TODO
        return true;
    }


    /**
     * 检查cookie(sessionId)
     * @return bool
     */
    public function checkCookie() {
        if(!empty($this->request->cookie) && !isset($this->request->cookie[SessionAdapter::SESSION_NAME])) {
            $this->setError('cookies没有sessionId');
            return false;
        }elseif (isset($this->request->cookie[SessionAdapter::SESSION_NAME])) {
            $value = $this->request->cookie[SessionAdapter::SESSION_NAME];
            $crypt = $this->_dependencyInjector->getShared('crypt');
            $decryptedValue = $crypt->decryptBase64($value);
            $uuid = $this->getAgentUuid();
            if(strpos($decryptedValue, $uuid)!==0) {
                $this->setError('cookies的sessionId错误');
                return false;
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
            $arr['host'] = $this->request->header['host'] ?? '';
            $arr['user-agent'] = $this->request->header['user-agent'] ?? '';
            $arr['accept'] = $this->request->header['accept'] ?? '';
            $arr['accept-language'] = $this->request->header['accept-language'] ?? '';
            $arr['accept-encoding'] = $this->request->header['accept-encoding'] ?? '';
            $arr['dnt'] = $this->request->header['dnt'] ?? '';
            $arr['connection'] = $this->request->header['connection'] ?? '';
            $arr['upgrade-insecure-requests'] = $this->request->header['upgrade-insecure-requests'] ?? '';
            sort($arr);

            $this->agentUuid = EncryptHelper::murmurhash3_int(json_encode($arr), 13, true);
        }

        return $this->agentUuid;
    }



    /**
     * 生产sessionId
     * @return string
     */
    public function makeSessionId() {
        if(is_null($this->sessionId)) {
            $uuid = $this->getAgentUuid();
            $ip = $this->request->header['x_forwarded_for'] ??
                ($this->request->header['client_ip'] ?? ($this->request->header['remote_addr'] ?? '127.0.0.1'));
            $rand = md5(uniqid($ip.microtime(true), true));

            $this->sessionId = "{$uuid}-{$rand}";
        }

        return $this->sessionId;
    }





}