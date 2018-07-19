<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/9/3
 * Time: 16:40
 * Desc: -swoole服务端异步日志类
 */


namespace Lkk\Phalwoo\Server\Component\Log;

use Monolog\Handler\HandlerInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Logger as Monologger;
use Lkk\Phalwoo\Server\Component\Log\Handler\AsyncStreamHandler;
use Phalcon\Events\Manager as PhEventManager;

class SwooleLogger extends Monologger {

    public static $maxFileSize   = 20971520; //日志文件大小限制20M
    public static $maxRecords    = 128; //最多N条日志记录
    public static $maxFileNum    = 20; //要保留的日志文件的最大数量,默认是0无限制

    protected $logFile;
    protected $defaultHandler;


    /**
     * 日志写入概率,1/N
     * @var int
     */
    protected $ratio = 1;

    /**
     * SwooleLogger constructor.
     *
     * @param string                   $name 日志名
     * @param array                    $handlers 日志处理类
     * @param array                    $processors 额外处理类
     */
    public function __construct($name, $handlers, $processors = []) {
        parent::__construct($name, $handlers, $processors);

        $this->useMicrosecondTimestamps(true);
    }



    public function setDefaultHandler($file) {
        $this->logFile = trim($file);
        if (!$this->logFile) {
            throw new \LogicException('You tried to log a empty logfile.');
        }


        //设置日期格式
        $dateFormat = "Y-m-d H:i:s.u";
        $formatter = new LineFormatter(null, $dateFormat);

        $this->defaultHandler = new AsyncStreamHandler($this->logFile, Monologger::INFO);
        $this->defaultHandler->setFormatter($formatter);
        $this->defaultHandler->setRatio($this->ratio);

        parent::pushHandler($this->defaultHandler);
    }


    public function getDefaultHandler() {
        return $this->defaultHandler;
    }


    public function pushHandler(HandlerInterface $handler) {
        $handlers = $this->getHandlers();
        if($handler instanceof AsyncStreamHandler && $handlers && current($handlers)===$handler) {
            return true;
        }

        return parent::pushHandler($handler); // TODO: Change the autogenerated stub
    }


    public function getCurrentHandler() {
        $handlers = $this->getHandlers();
        return $handlers ? current($handlers) : null;
    }


    public function setRatio($num=1) {
        $this->ratio = max(1, intval($num));
    }


    public function getRatio() {
        return $this->ratio;
    }



}