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
use Lkk\Helpers\ArrayHelper;
use Lkk\Helpers\DirectoryHelper;

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

    protected $maxFileSize = 20971520;
    protected $maxRecords = 64;
    protected $maxFileNum = 20;
    protected $writeBlocks = 64; //每次写入多少条
    protected $recordPools = []; //日志消息池,待转入recordBuffers
    protected $recordBuffers = []; //日志消息,待写入文件
    protected $logDir;
    protected $isWriting = false; //是否正在写日志

    /**
     * 日志写入概率,1/N
     * @var int
     */
    protected $ratio = 1;

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

        $conf = SwooleServer::getProperty('conf');
        $this->maxFileSize = $conf['sys_log']['file_size'] ?? SwooleLogger::$maxFileSize;
        $this->maxFileNum = $conf['sys_log']['max_files'] ?? SwooleLogger::$maxFileNum;
        $this->maxRecords = $conf['sys_log']['max_records'] ?? SwooleLogger::$maxRecords;

        $this->createDir();
        //$this->bindSwooleCloseEvent();
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
        if($this->ratio==1 || $this->countRecords() >= $this->maxRecords) {
            $this->recordBuffers += array_splice($this->recordPools, 0, ($this->maxRecords ? $this->maxRecords : $this->writeBlocks));

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
        $writeable = $all || !$this->isWriting || mt_rand(1, $this->ratio)==$this->ratio;
        if(!$writeable) return false;

        while (true) {
            $str = implode('', array_splice($this->recordBuffers, 0, $this->writeBlocks));
            if($all) {
                $str .= implode('', array_splice($this->recordPools, 0, $this->writeBlocks));
            }

            if(trim($str)=='') break;

            $logger = $this;
            $logger->setIsWriting(true);

            //在swoole的worker进程里面
            //因为swoole_async_writefile异步,但task不允许[can't use async-io in task process]
            if(SwooleServer::isWorker()) {
                swoole_async_writefile($this->url, $str, function($filename) use($logger) {
                    $logger->setIsWriting(false);
                    //echo "logger write done.\r\n";

                    //TODO 这里日志切割有问题,放到定时器里面切割?
                    if(file_exists($filename) && filesize($filename) >= $logger->maxFileSize){
                        $backupFile = $logger->logDir . '/' . basename($filename) . date('.YmdHis.') .'bak';
                        rename($filename, $backupFile);

                        $this->keepLogFiles();
                    }
                }, FILE_APPEND);
            }else{
                file_put_contents($this->url, $str, FILE_APPEND);
                $logger->setIsWriting(false);
            }
        }

        return true;
    }


    /**
     * 保持日志文件(日志文件数量检查,删除旧的日志文件)
     * @return bool
     */
    private function keepLogFiles() {
        $files = DirectoryHelper::getFileTree($this->logDir, 'file');
        if(empty($files) || $this->maxFileNum<=0) return false;

        $baseFile = pathinfo($this->url)['filename'];
        $baseName = pathinfo($this->url)['basename'];

        $arr = [];
        rsort($files, SORT_NATURAL);
        foreach ($files as $file) {
            $tmpBase = basename($file);
            if($baseName==$tmpBase) {
                continue;
            }
            if(mb_stripos($tmpBase, $baseFile)===0) {
                $item = [
                    'file' => $file,
                    'size' => filesize($file),
                    'time' => filemtime($file),
                ];

                array_push($arr, $item);
            }
        }

        $arr = ArrayHelper::arraySort($arr, 'time', 'DESC');
        $logNum = 0;
        foreach ($arr as $k=>$item) {
            $logNum++;
            if($logNum > $this->maxFileNum) {
                unlink($item['file']);
                unset($arr[$k]);
            }
        }

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


    /**
     * 绑定[客户端连接关闭]事件
     * @return $this
     */
    public function bindSwooleCloseEvent() {
        $di = SwooleServer::getServerDi();
        $logger = $this;
        $eventManager = $di->get('eventsManager');
        $eventManager->attach('SwooleServer:onSwooleClose', function () use($logger) {
            //echo "logger event callback\r\n";
            $logger->flush();
            return true;
        });

        return $this;
    }


    public function setRatio($num=1) {
        $this->ratio = max(1, intval($num));
    }


    public function getRatio() {
        return $this->ratio;
    }


}