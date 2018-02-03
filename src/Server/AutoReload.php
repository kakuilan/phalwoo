<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/12/15
 * Time: 10:52
 * Desc: -自动热更新[须inotify扩展]
 * 注意: 作为popen子进程运行时,不能有任何输出
 */

namespace Lkk\Phalwoo\Server;

use Lkk\Helpers\CommonHelper;
use Lkk\Phalwoo\Server\SwooleServer;
use Lkk\LkkService;


class AutoReload extends LkkService{

    /**
     * @var resource
     */
    public $serverPid = 0;
    public $afterMillSeconds = 10000; //毫秒,默认10秒
    protected $inotify;
    protected $reloadFileTypes = ['.php' => true];
    protected $watchFiles = [];

    //热更新守护进程pid文件
    public static $defaultName = 'reload';
    public static $prcessTitle = 'phalwoo_inotify';
    public static $selfPidFile;
    public static $selfLogFile;
    public static $restartFunc;

    /**
     * 正在reload
     */
    protected $reloading = false;
    protected $events;

    /**
     * 根目录
     * @var array
     */
    protected $rootDirs = [];


    /**
     * 设置自身pid文件路径
     * @param string $path
     */
    public static function setSelfPidPath($path='') {
        $dir = dirname($path);
        if(!CommonHelper::isReallyWritable($dir)) {
            self::$selfPidFile = '/tmp/'.self::$defaultName.'.pid';
        }else{
            self::$selfPidFile = $path;
        }

        touch(self::$selfPidFile);
    }


    /**
     * 设置自身log日志路径
     * @param string $path
     */
    public static function setSelfLogPath($path='') {
        $dir = dirname($path);
        if(!CommonHelper::isReallyWritable($dir)) {
            self::$selfPidFile = '/tmp/'.self::$defaultName.'.log';
        }else{
            self::$selfLogFile = $path;
        }

        touch(self::$selfLogFile);
    }


    /**
     * 设置进程重启的函数
     * @param callable $func
     */
    public static function setRestartFunc(callable $func) {
        self::$restartFunc = $func;
    }


    /**
     * 写入自身pid到文件
     * @param int $pid
     * @param string $file
     * @return bool|int
     */
    public static function writeSelfPidFile($pid=0, $file='') {
        if(empty($file)) $file = self::$selfPidFile;
        if(empty($file)) return false;
        return file_put_contents($file, $pid);
    }


    /**
     * 获取热更新自身的进程pid
     * @return int
     */
    public static function getSelfPid() {
        return file_exists(self::$selfPidFile) ? intval(file_get_contents(self::$selfPidFile)) : 0;
    }


    /**
     * 设置进程标题
     * @param string $title
     */
    public static function setPrcessTitle($title='') {
        if(is_string($title) && !empty($title)) self::$prcessTitle = $title;
    }


    /**
     * 获取进程标题
     * @return string
     */
    public static function getPrcessTitle() {
        return self::$prcessTitle;
    }


    /**
     * 记录日志
     * @param string $msg
     * @return bool
     */
    public static function log($msg='') {
        if(empty($msg)) return false;
        $log = "[".date('Y-m-d H:i:s')."]\t".$msg."\r\n";
        file_put_contents(self::$selfLogFile, $log, FILE_APPEND);

        return true;
    }


    /**
     * AutoReload constructor.
     * @param array $vars
     */
    public function __construct($vars) {
        parent::__construct($vars);

        $this->serverPid = $vars['serverPid'] ?? 0;
        $this->afterMillSeconds = $vars['afterMillSeconds'] ?? 10000;

        if (!$this->serverPid || posix_kill($this->serverPid, 0) === false) {
            die("Error!Server process#[{$this->serverPid}] not found.\r\n");
        }

        $this->inotify = inotify_init();
        $this->events = IN_MODIFY | IN_DELETE | IN_CREATE | IN_MOVE;
        swoole_event_add($this->inotify, function ($ifd) {
            $events = inotify_read($this->inotify);
            if (!$events) {
                return;
            }

            foreach($events as $ev) {
                if ($ev['mask'] == IN_IGNORED) {
                    continue;
                }else if ($ev['mask'] == IN_CREATE or $ev['mask'] == IN_DELETE or $ev['mask'] == IN_MODIFY or $ev['mask'] == IN_MOVED_TO or $ev['mask'] == IN_MOVED_FROM) {
                    $fileType = strrchr($ev['name'], '.');
                    //非重启类型
                    if (!isset($this->reloadFileTypes[$fileType])) {
                        continue;
                    }
                }
                //正在reload，不再接受任何事件，冻结N毫秒
                if (!$this->reloading) {
                    self::log("after {$this->afterMillSeconds} milliseconds reload the server");
                    //有事件发生了，进行重启
                    swoole_timer_after($this->afterMillSeconds, array($this, 'reload'));
                    $this->reloading = true;
                }
            }
        });
    }


    /**
     * 重载
     */
    public function reload() {
        $currPid = getmypid();
        $workPid = self::getSelfPid();
        $msg = "reloading...: currPid:[{$currPid}] workPid:[{$workPid}] serverPid[{$this->serverPid}]";

        self::log($msg);
        //向主进程发送信号
        posix_kill($this->serverPid, SIGUSR1);
        //清理所有监听
        $this->clearWatch();
        //重新监听
        foreach($this->rootDirs as $root) {
            $this->watch($root);
        }
        //继续进行reload
        $this->reloading = false;
    }


    /**
     * 添加文件类型
     * @param $type
     */
    public function addFileType($type) {
        $type = trim($type, '.');
        $this->reloadFileTypes['.' . $type] = true;
    }

    /**
     * 添加事件
     * @param $inotifyEvent
     */
    public function addEvent($inotifyEvent) {
        $this->events |= $inotifyEvent;
    }

    /**
     * 清理所有inotify监听
     */
    public function clearWatch() {
        foreach($this->watchFiles as $wd) {
            inotify_rm_watch($this->inotify, $wd);
        }
        $this->watchFiles = [];
    }

    /**
     * 监控目录
     * @param string|array $dir
     * @param bool $root
     * @return bool
     */
    public function watch($dir, $root = true) {
        //检查目录
        if(is_string($dir)) {
            if(!is_dir($dir)) die("Error![$dir] is not a directory.");
        }

        $dirs = (array)$dir;
        foreach ($dirs as $k=>&$dir) {
            $dir = rtrim($dir, '/');
            if(!is_dir($dir)) {
                unset($dirs[$k]);
                continue;
            }elseif (isset($this->watchFiles[$dir])) { //避免重复监听
                continue;
            }

            //根目录
            if($root) $this->rootDirs[] = $dir;
        }

        if(empty($dirs)) {
            die("Error!\$dir is empty.");
        }
        sort($dirs);

        //遍历监听目录
        foreach ($dirs as $dir) {
            $wd = inotify_add_watch($this->inotify, $dir, $this->events);
            $this->watchFiles[$dir] = $wd;
            $files = scandir($dir);
            foreach ($files as $f) {
                if ($f == '.' or $f == '..') {
                    continue;
                }
                $path = $dir . '/' . $f;
                //递归目录
                if (is_dir($path)) {
                    $this->watch($path, false);
                }
                //检测文件类型
                $fileType = strrchr($f, '.');
                if (isset($this->reloadFileTypes[$fileType])) {
                    $wd = inotify_add_watch($this->inotify, $path, $this->events);
                    $this->watchFiles[$path] = $wd;
                }
            }
        }

        return true;
    }


    /**
     * 重置(结束旧进程)
     */
    public function reset() {
        $currPid = getmypid();
        $lastPid = self::getSelfPid();

        self::writeSelfPidFile($currPid);
        SwooleServer::setProcessTitle(self::$prcessTitle);
        swoole_timer_after(1000, function () use ($lastPid, $currPid) {
            $msg = "timer_after: lastPid:[{$lastPid}] currPid:[{$currPid}]";
            self::log($msg);

            swoole_timer_tick(200, function () use ($lastPid, $currPid){
                $workPid = self::getSelfPid(); //被更新的运行时pid
                if($workPid>0 && $workPid!=$currPid) {
                    $msg = "reloadService exit: lastPid:[{$lastPid}] currPid:[{$currPid}] workPid:[{$workPid}]";
                    self::log($msg);
                    die($msg ."\r\n");
                }elseif ($workPid==0) { //停止
                    $msg = "reloadService stop: lastPid:[{$lastPid}] currPid:[{$currPid}] workPid:[{$workPid}]";
                    self::log($msg);
                    die($msg ."\r\n");
                }
            });
        });
    }


    /**
     * 停止
     */
    public static function setStop() {
        self::writeSelfPidFile(0);
    }



    /**
     * 退出时检查
     */
    public function __destruct() {
        $currPid = getmypid();
        $workPid = self::getSelfPid();
        $msg = "reloadService destruct: currPid:[{$currPid}] workPid:[{$workPid}]";
        self::log($msg);

        //在另一个进程重启
        if($workPid > 0 && $currPid==$workPid) {
            $this->reload();
            call_user_func(self::$restartFunc);
        }
    }


    /**
     * 开始运行
     */
    public function run() {
        $this->reset();
        swoole_event_wait();
    }



}