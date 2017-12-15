<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/12/15
 * Time: 10:52
 * Desc: -自动热更新[须inotify扩展]
 */

namespace Lkk\Phalwoo\Server;

use \Exception;
use Lkk\Helpers\CommonHelper;
use Lkk\Phalwoo\Server\SwooleServer;

class AutoReload {

    /**
     * @var resource
     */
    protected $inotify;
    protected $pid;
    protected $reloadFileTypes = ['.php' => true];
    protected $watchFiles = [];
    protected $afterNSeconds = 10;

    //热更新守护进程pid文件
    public static $prcessTitle = 'phalwoo_inotify';
    public static $selfPidFile;

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
        if(!CommonHelper::isReallyWritable($path)) {
            self::$selfPidFile = '/tmp/'.self::$prcessTitle.'.pid';
        }else{
            self::$selfPidFile = $path;
        }
    }


    /**
     * 写入自身pid到文件
     * @param int $pid
     */
    public static function writeSelfPidFile($pid=0) {
        file_put_contents(self::$selfPidFile, $pid);
    }


    /**
     * 获取热更新自身的进程pid
     * @return int
     */
    public static function getSelfPid() {
        return file_exists(self::$selfPidFile) ? intval(file_get_contents(self::$selfPidFile)) : 0;
    }


    /**
     * 输出日志
     * @param $log
     */
    public function putLog($log) {
        $_log = "[".date('Y-m-d H:i:s')."]\t".$log."\n";
        echo $_log;
    }


    /**
     * @param $serverPid
     * @throws Exception
     */
    public function __construct($serverPid) {
        $this->pid = $serverPid;
        if (posix_kill($serverPid, 0) === false) {
            throw new Exception("Process#$serverPid not found.");
        }
        $this->inotify = inotify_init();
        $this->events = IN_MODIFY | IN_DELETE | IN_CREATE | IN_MOVE;
        swoole_event_add($this->inotify, function ($ifd) {
            $events = inotify_read($this->inotify);
            if (!$events) {
                return;
            }
            var_dump($events);
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
                //正在reload，不再接受任何事件，冻结10秒
                if (!$this->reloading) {
                    $this->putLog("after 10 seconds reload the server");
                    //有事件发生了，进行重启
                    swoole_timer_after($this->afterNSeconds * 1000, array($this, 'reload'));
                    $this->reloading = true;
                }
            }
        });
    }


    /**
     * 重载
     */
    public function reload() {
        $this->putLog("reloading");
        //向主进程发送信号
        posix_kill($this->pid, SIGUSR1);
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
        $this->watchFiles = array();
    }

    /**
     * 监控目录
     * @param $dir
     * @param bool $root
     * @return bool
     * @throws Exception
     */
    public function watch($dir, $root = true) {
        //目录不存在
        if (!is_dir($dir)) {
            throw new Exception("[$dir] is not a directory.");
        }
        //避免重复监听
        if (isset($this->watchFiles[$dir])) {
            return false;
        }
        //根目录
        if ($root) {
            $this->rootDirs[] = $dir;
        }
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
        return true;
    }


    /**
     * 开始运行
     */
    public function run() {
        $lastPid = self::getSelfPid();
        $currPid = getmypid();
        if($lastPid>0 && $lastPid!=$currPid) { //结束旧进程
            while (1) {
                $masterIsAlive = $lastPid && posix_kill($lastPid, 0);
                if ($masterIsAlive) {
                    // Waiting amoment.
                    echo "... ";
                    sleep(1);
                    continue;
                }
                echo("Service ".self::$prcessTitle." kill success\r\n");
                break;
            }
        }

        SwooleServer::setProcessTitle(self::$prcessTitle);
        self::writeSelfPidFile($currPid);

        swoole_event_wait();
    }



}