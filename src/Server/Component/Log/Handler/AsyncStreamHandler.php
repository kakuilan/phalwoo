<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/9/3
 * Time: 17:21
 * Desc: -异步日志流处理
 */


namespace Lkk\Phalwoo\Server\Component\Log\Handler;

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use Lkk\Phalwoo\Server\Component\Log\SwooleLogger;
use Lkk\Phalwoo\Server\SwooleServer;

/**
 * Stores to any stream resource
 *
 * Can be used to store into php://stderr, remote and local files, etc.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class AsyncStreamHandler extends AbstractProcessingHandler {

    protected $stream;
    protected $url;
    private $errorMessage;
    protected $filePermission;
    protected $useLocking;
    private $dirCreated;

    protected $maxFileSize = 0;
    protected $maxRecords = 0;
    protected $recordPools = []; //日志消息池,待转入recordBuffers
    protected $recordBuffers = []; //日志消息,待写入文件
    protected $logDir;
    protected $isWriting = false; //是否正在写日志

    /**
     * @param resource|string $stream
     * @param int             $level          The minimum logging level at which this handler will be triggered
     * @param Boolean         $bubble         Whether the messages that are handled can bubble up the stack or not
     * @param int|null        $filePermission Optional file permissions (default (0644) are only for owner read/write)
     * @param Boolean         $useLocking     Try to lock log file before doing any writes
     *
     * @throws \Exception                If a missing directory is not buildable
     * @throws \InvalidArgumentException If stream is not a resource or string
     */
    public function __construct($stream, $level = Logger::DEBUG, $bubble = true, $filePermission = null, $useLocking = false) {
        parent::__construct($level, $bubble);
        if (is_resource($stream)) {
            $this->stream = $stream;
        } elseif (is_string($stream)) {
            $this->url = $stream;
        } else {
            throw new \InvalidArgumentException('A stream must either be a resource or a string.');
        }

        $this->filePermission = $filePermission;
        $this->useLocking = $useLocking;

        $this->maxFileSize = SwooleLogger::$maxFileSize;
        $this->maxRecords = SwooleLogger::$maxRecords;

        $this->createDir();
    }


    /**
     * 析构函数
     */
    public function __destruct(){
        $this->flush();
    }


    public function setIsWriting(bool $status) {
        $this->isWriting = $status;
    }


    /**
     * 设置单个日志文件最大尺寸
     * @param int $size
     */
    public function setMaxFileSize(int $size) {
        if($size > 0) {
            $this->maxFileSize = $size;
        }
    }


    /**
     * 设置日志池最大尺寸
     * @param int $num
     */
    public function setMaxRecords(int $num) {
        if($num > 0) {
            $this->maxRecords = $num;
        }
    }


    /**
     * 统计[未写入文件的]日志数量
     * @param bool $all
     *
     * @return int
     */
    public function countRecords($all=false) {
        $num = count($this->recordPools);
        if($all) $num += count($this->recordBuffers);
        return $num;
    }



    /**
     * {@inheritdoc}
     */
    public function close() {
        $this->stream = null;
    }

    /**
     * Return the currently active stream if it is open
     *
     * @return resource|null
     */
    public function getStream() {
        return $this->stream;
    }


    /**
     * Return the stream URL if it was configured with a URL and not an active resource
     *
     * @return string|null
     */
    public function getUrl() {
        return $this->url;
    }


    /**
     * {@inheritdoc}
     */
    protected function write(array $record) {
        array_push($this->recordPools, strval($record['formatted']));
        if($this->countRecords() >= $this->maxRecords) {
            $this->recordBuffers += array_splice($this->recordPools, 0, $this->maxRecords);

            //异步写日志文件
            $this->streamWrite(false);
        }
    }


    public function flush() {
        if($this->recordPools) {
            $this->streamWrite(true);
        }
        $this->recordPools = [];
        $this->recordBuffers = [];
    }


    protected function streamWrite($all=false) {
        //允许一个随机率可写,否则可能内存不足
        $writeable = !$all || !$this->isWriting || mt_rand(0, 3)==1;
        if(!$writeable) return false;

        $str = implode('', array_splice($this->recordBuffers, 0));
        if($all) {
            $str .= implode('', array_splice($this->recordPools, 0));
        }

        //echo $str;die;

        $logger = $this;
        $logger->setIsWriting(true);
        swoole_async_writefile($this->url, $str, function($filename) use($logger) {
            $logger->setIsWriting(false);
            echo "write done.\r\n";

            //TODO 这里日志切割有问题,放到定时器里面切割?
            if(file_exists($filename) && filesize($filename) >= $logger->maxFileSize){
                $backupFile = $logger->logDir . '/' . basename($filename) . date('.YmdHis.') .'bak';
                rename($filename, $backupFile);
            }
        }, FILE_APPEND);

        return true;
    }


    private function customErrorHandler($code, $msg) {
        $this->errorMessage = preg_replace('{^(fopen|mkdir)\(.*?\): }', '', $msg);
    }


    /**
     * @param string $stream
     *
     * @return null|string
     */
    private function getDirFromStream($stream) {
        $pos = strpos($stream, '://');
        if ($pos === false) {
            return dirname($stream);
        }

        if ('file://' === substr($stream, 0, 7)) {
            return dirname(substr($stream, 7));
        }

        return null;
    }


    private function createDir() {
        // Do not try to create dir if it has already been tried.
        if ($this->dirCreated) {
            return;
        }

        $dir = $this->getDirFromStream($this->url);
        if (null !== $dir && !is_dir($dir)) {
            $this->errorMessage = null;
            set_error_handler(array($this, 'customErrorHandler'));
            $status = mkdir($dir, 0777, true);
            restore_error_handler();
            if (false === $status) {
                throw new \UnexpectedValueException(sprintf('There is no existing directory at "%s" and its not buildable: '.$this->errorMessage, $dir));
            }
        }

        $this->logDir = $dir;
        $this->dirCreated = true;
    }


    public function bindServerStopEvent() {
        $di = SwooleServer::getServerDi();
        $logger = $this;
        $eventManager = $di->get('eventsManager');
        $eventManager->attach('SwooleServer:onClose', function () use($logger) {
            echo "event callback\r\n";
            $logger->flush();
        });

    }
}



