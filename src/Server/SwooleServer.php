<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 17-9-4
 * Time: 下午6:18
 * Desc: -SWOOLE服务器
 */


namespace Lkk\Phalwoo\Server;

use Lkk\LkkService;
use Lkk\Helpers\CommonHelper;
use Lkk\Helpers\ValidateHelper;
use Lkk\Phalwoo\Server\Component\Log\SwooleLogger;
use Lkk\Phalwoo\Server\Component\Log\Handler\AsyncStreamHandler;
use Phalcon\Di\FactoryDefault\Cli as CliDi;
use Phalcon\Events\Manager as PhEventManager;
use Lkk\Phalwoo\Server\Component\Pool\PoolManager;

class SwooleServer extends LkkService {

    protected $conf; //服务配置
    protected $events; //swoole_server事件
    protected $server; //swoole_server对象
    protected $serverDi; //服务器的DI容器

    protected $inerqueue; //内部队列,非持久化工作
    protected $rediqueue; //redis持久化队列

    protected $servName; //服务名
    protected $listenIP; //监听IP
    protected $listenPort; //监听端口

    protected $timerTaskManager; //定时任务管理器
    protected $logger; //系统日志对象
    protected $poolManager; //连接池管理对象

    //命令行操作列表
    public static $cliOperations = [
        'status',
        'start',
        'stop',
        'restart',
        'reload',
        'kill',
    ];


    protected static $cliOperate; //当前命令操作
    protected static $daemonize; //是否以守护进程启动
    protected static $pidFile;   //pid文件路径
    protected static $startTime; //服务启动时间,毫秒


    /**
     * 构造函数
     * SwooleServer constructor.
     * @param array $vars
     */
    public function __construct(array $vars = []) {
        parent::__construct($vars);

    }


    /**
     * 获取实例[重写]
     * @param array $vars
     * @return mixed
     */
    public static function instance(array $vars = []) {
        if(is_null(self::$instance) || !is_object(self::$instance)) {
            self::$instance = new self($vars);
        }

        return self::$instance;
    }


    /**
     * 设置实例的属性
     * @param $name
     * @param $val
     * @return bool
     */
    final public static function setProperty($name, $val) {
        $res = false;
        if(is_object(self::$instance)) {
            try {
                self::$instance->$name = $val;
                $res = true;
            }catch (\Exception $e) {
                $res = false;
            }
        }

        return $res;
    }


    /**
     * 获取实例的属性
     * @param $name
     * @return null
     */
    final public static function getProperty($name) {
        $res = null;
        if(is_object(self::$instance)) {
            if(isset(self::$instance->$name) || array_key_exists($name, get_class_vars(get_class(self::$instance)))) {
                try {
                    $res = self::$instance->$name;
                }catch (\Exception $e) {
                    $res = null;
                }
            }
        }

        return $res;
    }


    /**
     * 获取SWOOLE服务
     * @return mixed
     */
    final public static function getServer() {
        return (is_null(self::$instance) || !is_object(self::$instance)) ? null : self::$instance->server;
    }


    /**
     * 设置DI容器
     */
    public function setServerDi() {
        $this->serverDi = new CliDi();
    }


    /**
     * 获取服务器的DI容器
     * @return mixed
     */
    final public static function getServerDi() {
        return (is_null(self::$instance) || !is_object(self::$instance)) ? null : self::$instance->serverDi;
    }


    /**
     * 设置定时任务管理器
     */
    public function setTimerTaskManager() {
        $this->timerTaskManager = new TimerTaskManager(['timerTasks'=>$this->conf['timer_tasks']]);
    }


    /**
     * 获取定时任务管理器
     * @return mixed
     */
    final public static function getTimerTaskManager() {
        return (is_null(self::$instance) || !is_object(self::$instance)) ? null : self::$instance->timerTaskManager;
    }


    /**
     * 设置系统日志对象
     */
    public function setLogger() {
        $this->logger = new SwooleLogger($this->conf['sys_log']['name'], [], []);
        $this->logger->setDefaultHandler($this->conf['sys_log']['file']);
    }


    /**
     * 获取系统日志对象
     * @return mixed
     */
    final public static function getLogger() {
        return (is_null(self::$instance) || !is_object(self::$instance)) ? null : self::$instance->logger;
    }


    /**
     * 设置连接池管理对象
     * @param array $conf
     */
    public function setPoolManager(array $conf) {
        $this->poolManager = PoolManager::getInstance();
        $this->poolManager->setConf($conf);
    }


    /**
     * 获取连接池管理对象
     * @return null
     */
    final public static function getPoolManager() {
        return (is_null(self::$instance) || !is_object(self::$instance)) ? null : self::$instance->poolManager;
    }


    /**
     * 设置内置队列对象
     */
    protected function setInerQueue() {
        $this->inerqueue = new \Swoole\Channel(256 * 1024 * 1024);
    }


    /**
     * 获取内置队列对象
     * @return mixed
     */
    final public static function getInerQueue() {
        return (is_null(self::$instance) || !is_object(self::$instance)) ? null : self::$instance->inerqueue;
    }


    /**
     * 设置redis队列对象
     */
    protected function setRediQueue() {
        //默认使用工作流队列,子类可自行更改
        $this->rediqueue = RedisQueue::getQueueObject(RedisQueue::APP_WORKFLOW_QUEUE_NAME, []);
    }


    /**
     * 获取redis队列对象
     * @return mixed
     */
    final public static function getRediQueue() {
        return (is_null(self::$instance) || !is_object(self::$instance)) ? null : self::$instance->rediqueue;
    }


    /**
     * CLI命令行用法
     */
    public static function cliUsage() {
        $operates = implode(' | ', self::$cliOperations);
        echo "usage:\r\nphp app/index.php {$operates} [-d]\r\n";
    }


    /**
     * 解析CLI命令参数
     */
    public static function parseCommands() {
        self::$startTime = CommonHelper::getMillisecond();

        if (php_sapi_name() != 'cli') {
            exit("only run in command line mode \r\n");
        }

        //销毁旧实例
        self::destroy();

        global $argv;
        self::$cliOperate = isset($argv[1]) ? strtolower($argv[1]) : '';
        self::$daemonize = (isset($argv[2]) && '-d'==strtolower($argv[2])) ? 1 : 0;

        if(!in_array(self::$cliOperate, self::$cliOperations)) {
            self::cliUsage();
            exit(1);
        }
    }


    /**
     * 获取服务启动时间,毫秒
     * @return mixed
     */
    public static function getServerStartTime() {
        return self::$startTime;
    }


    /**
     * 获取守护设置(是否守护进程)
     * @return int
     */
    public static function getDaemonize() {
        return intval(self::$daemonize);
    }


    /**
     * 设置配置
     * @param array $conf
     */
    public function setConf(array $conf) {
        $this->conf = $conf;
        $this->servName = $this->conf['server_name'];
        $this->listenIP = $this->conf['http_server']['host'];
        $this->listenPort = $this->conf['http_server']['port'];

        self::$pidFile = self::getPidPath($conf);

        return $this;
    }


    /**
     * 获取PID文件路径
     * @param $conf
     *
     * @return string
     */
    public static function getPidPath($conf) {
        $res = '';
        if(empty($conf)) return $res;

        $fileName = strtolower($conf['server_name']) .'-'. $conf['http_server']['host'] .'-'. $conf['http_server']['port'] .'.pid';
        $res = rtrim(str_replace('\\', '/', $conf['pid_dir']), '/') . DS . $fileName;
        return $res;
    }


    /**
     * 设置进程标题
     * @param $title
     */
    public static function setProcessTitle($title) {
        // >=php 5.5
        if (function_exists('cli_set_process_title')) {
            cli_set_process_title($title);
        } // Need proctitle when php<=5.5 .
        else {
            swoole_set_process_name($title);
        }
    }


    /**
     * 检查扩展
     * @return bool
     */
    public static function checkExtensions() {
        $res = true;
        //检查是否已安装swoole和phalcon
        if(!extension_loaded('swoole')) {
            print_r("no swoole extension!\n");
            $res = false;
        }elseif (!extension_loaded('phalcon')) {
            print_r("no phalcon extension!\n");
            $res = false;
        }elseif (!extension_loaded('inotify')) {
            print_r("no inotify extension!\n");
            $res = false;
        }elseif (!extension_loaded('redis')) {
            print_r("no redis extension!\n");
            $res = false;
        }elseif (!extension_loaded('pdo')) {
            print_r("no pdo extension!\n");
            $res = false;
        }elseif (!class_exists('swoole_redis')) {
            print_r("Swoole compilation is missing --enable-async-redis!\n");
            $res = false;
        }

        return $res;
    }


    /**
     * 设置主进程PID
     * @param $masterPid
     * @param $managerPid
     */
    public static function setMasterPid($masterPid, $managerPid) {
        file_put_contents(self::$pidFile, $masterPid);
        file_put_contents(self::$pidFile, ',' . $managerPid, FILE_APPEND);
    }


    /**
     * 设置WORKER进程PID
     * @param $workerPid
     */
    public static function setWorketPid($workerPid) {
        file_put_contents(self::$pidFile, ',' . $workerPid, FILE_APPEND);
    }


    /**
     * 添加事件
     * @param string $eventName 事件名称
     * @param callable $eventFunc 事件闭包函数
     * @param array $funcParam 事件参数
     * @return $this
     */
    public function addEvent(string $eventName, callable $eventFunc, array $funcParam=[]) {
        if(method_exists($this, $eventName) && substr($eventName, 0, 2)==='on') {
            $this->events[$eventName] = [
                'func' => $eventFunc,
                'parm' => $funcParam
            ];
        }

        return $this;
    }


    /**
     * 获取外部扩展事件
     * @param string $eventName 事件名称
     * @return bool|mixed
     */
    public function getExtEvent(string $eventName) {
        return empty($eventName) ? false : (isset($this->events[$eventName]) ? $this->events[$eventName] : false);
    }


    /**
     * 事件调用
     * @param string $eventName 事件名称
     */
    public function eventFire(string $eventName) {
        $extEvent = $this->getExtEvent($eventName);
        if($extEvent) {
            call_user_func_array($extEvent['func'], $extEvent['parm']);
        }
    }



    /**
     * 执行
     * @return $this
     */
    public function run() {
        $chkExts = self::checkExtensions();
        if(!$chkExts) exit(1);

        $pidExis = file_exists(self::$pidFile);
        $masterIsAlive = false;
        $masterPid = $managerPid = 0;
        if($pidExis) {
            $pids = explode(',', file_get_contents(self::$pidFile));
            $masterPid = $pids[0];
            $managerPid = $pids[1];
            $masterIsAlive = $masterPid && @posix_kill($masterPid, 0);
        }

        $binded  = ValidateHelper::isPortBinded('127.0.0.1', $this->listenPort);
        $msg = '';
        $timeout = 10;
        switch (self::$cliOperate) {
            case 'status' : //查看服务状态
                if($masterIsAlive) {
                    $msg .= "Service $this->servName is running...\r\n";
                }else{
                    $msg .= "Service $this->servName not running!!!\r\n";
                }

                if($binded) {
                    $msg .= "Port $this->listenPort is binded...\r\n";
                }else{
                    $msg .= "Port $this->listenPort not binded!!!\r\n";
                }

                echo $msg;
                break;
            case 'start' :
                if($masterIsAlive) {
                    $msg .= "Service $this->servName already running...\r\n";
                    echo $msg;
                    exit(1);
                }elseif ($binded) {
                    $msg .= "Port $this->listenPort already binded...\r\n";
                    echo $msg;
                    exit(1);
                }

                $this->initServer()->startServer();

                break;
            case 'stop' :
                if(!$binded) {
                    $msg = "Service $this->servName not running!!!\r\n";
                    echo $msg;
                    exit(1);
                }

                @unlink(self::$pidFile);
                echo("Service $this->servName is stoping ...\r\n");
                $masterPid && posix_kill($masterPid, SIGTERM);
                $startTime = time();
                while (1) {
                    $masterIsAlive = $masterPid && posix_kill($masterPid, 0);
                    if ($masterIsAlive) {
                        if (time() - $startTime >= $timeout) {
                            echo("Service $this->servName stop fail\r\n");
                            exit;
                        }
                        // Waiting amoment.
                        echo "... ";
                        sleep(1);
                        continue;
                    }
                    echo("Service $this->servName stop success\r\n");
                    break;
                }
                exit(0);
                break;
            case 'restart' :
                @unlink(self::$pidFile);
                echo("Service $this->servName is stoping ...\r\n");
                $masterPid && posix_kill($masterPid, SIGTERM);
                $startTime = time();
                while (1) {
                    $masterIsAlive = $masterPid && posix_kill($masterPid, 0);
                    if ($masterIsAlive) {
                        if (time() - $startTime >= $timeout) {
                            echo("Service $this->servName stop fail\r\n");
                            exit;
                        }
                        // Waiting amoment.
                        echo "... ";
                        sleep(1);
                        continue;
                    }
                    echo("Service $this->servName stop success\r\n");
                    break;
                }

                self::$daemonize = true;
                $this->initServer()->startServer();

                break;
            case 'reload' :
                posix_kill($managerPid, SIGUSR1);
                echo("Service $this->servName reload\r\n");
                exit(0);
                break;
            case 'kill' :
                @unlink(self::$pidFile);
                $bash = "ps -ef|grep {$this->servName}|grep -v grep|cut -c 9-15|xargs kill -9";
                exec($bash);
                break;
            default :
                self::cliUsage();
                exit(1);
                break;
        }

        return $this;
    }



    /**
     * 初始化服务
     * @return $this
     */
    public function initServer() {
        $this->setInerQueue();
        $this->setRediQueue();
        $this->setServerDi();
        $this->setTimerTaskManager();
        $this->setLogger();

        $httpCnf = $this->conf['http_server'];
        $this->server = new \swoole_http_server($httpCnf['host'], $httpCnf['port']);

        $servCnf = $this->conf['server_conf'];
        $servCnf['daemonize'] = self::getDaemonize();
        $this->server->set($servCnf);

        return $this;
    }


    /**
     * 启动服务
     * @return $this
     */
    public function startServer() {
        $this->bindEvents();

        echo("Service $this->servName start success\r\n");
        $this->server->start();

        return $this;
    }


    /**
     * 关闭服务
     * @return $this
     */
    public function shutdownServer() {
        //重启所有worker进程
        $this->server->shutdown();
        return $this;
    }


    /**
     * 停止worker
     * @return $this
     */
    public function stopWorker() {
        //使当前worker进程停止运行
        $this->server->stop();
        return $this;
    }


    /**
     * 重载worker
     * @return $this
     */
    public function reloadWorkers() {
        $this->server->reload();
        return $this;
    }


    /**
     * 判断当前进程是否为Worker进程
     * @return bool
     */
    public static function isWorker() {
        $server = self::getServer();
        if(empty($server) || !isset($server->taskworker)) {
            return true;
        }

        return !$server->taskworker;
    }



    /**
     * 获取当前服务器ip
     * @return string
     */
    public static function getLocalIp() {
        static $currentIP;

        if ($currentIP == null) {
            $serverIps = \swoole_get_local_ip();
            $patternArray = array(
                '10\.',
                '172\.1[6-9]\.',
                '172\.2[0-9]\.',
                '172\.31\.',
                '192\.168\.'
            );
            foreach ($serverIps as $serverIp) {
                // 匹配内网IP
                if (preg_match('#^' . implode('|', $patternArray) . '#', $serverIp)) {
                    $currentIP = $serverIp;
                    return $currentIP;
                }
            }
        }

        return $currentIP;
    }


    /**
     * 绑定事件
     */
    final public function bindEvents() {
        $this->server->on('Start',          self::getCallbackMethod('onSwooleStart'));
        $this->server->on('Shutdown',       self::getCallbackMethod('onSwooleShutdown'));
        $this->server->on('WorkerStart',    self::getCallbackMethod('onSwooleWorkerStart'));
        $this->server->on('WorkerStop',     self::getCallbackMethod('onSwooleWorkerStop'));
        $this->server->on('Connect',        self::getCallbackMethod('onSwooleConnect'));
        $this->server->on('Request',        self::getCallbackMethod('onSwooleRequest'));
        $this->server->on('Close',          self::getCallbackMethod('onSwooleClose'));
        $this->server->on('Task',           self::getCallbackMethod('onSwooleTask'));
        $this->server->on('Finish',         self::getCallbackMethod('onSwooleFinish'));
        $this->server->on('PipeMessage',    self::getCallbackMethod('onSwoolePipeMessage'));
        $this->server->on('WorkerError',    self::getCallbackMethod('onSwooleWorkerError'));
        $this->server->on('ManagerStart',   self::getCallbackMethod('onSwooleManagerStart'));
        $this->server->on('ManagerStop',    self::getCallbackMethod('onSwooleManagerStop'));

        return $this;
    }


    /**
     * 获取服务器回调方法名
     * @param string $methodName 方法
     * @return string
     */
    public static function getCallbackMethod(string $methodName) {
        if(method_exists(static::class, $methodName)) {
            $class = static::class;
        }else{
            $class = self::class;
        }
        $callback = "$class::$methodName";

        return $callback;
    }


    /**
     * 当服务器启动时[事件]
     * @param object $serv swoole_server对象
     */
    public static function onSwooleStart($serv) {
        $servName = self::getProperty('servName');
        self::setProcessTitle($servName.'-Master');
        self::setMasterPid($serv->master_pid, $serv->manager_pid);

        self::instance()->eventFire(__FUNCTION__);
        echo "Master Start...\r\n";

    }


    /**
     * 当服务器关闭时[事件]
     * @param object $serv swoole_server对象
     */
    public static function onSwooleShutdown($serv) {
        self::instance()->eventFire(__FUNCTION__);
        echo "Master Shutdown...\r\n";

    }


    /**
     * 当管理进程启动时[事件]
     * @param object $serv swoole_server对象
     */
    public static function onSwooleManagerStart($serv) {
        $servName = self::getProperty('servName');
        self::setProcessTitle($servName.'-Manager');

        self::instance()->eventFire(__FUNCTION__);
        echo "Manager Start...\r\n";

    }


    /**
     * 当管理进程停止时[事件]
     * @param object $serv swoole_server对象
     */
    public static function onSwooleManagerStop($serv) {
        self::instance()->eventFire(__FUNCTION__);
        echo "Manager Stop...\r\n";

    }


    /**
     * 当worker进程启动[事件]
     * @param object $serv swoole_server对象
     * @param int $workerId 从0-$worker_num之间的数字
     */
    public static function onSwooleWorkerStart($serv, $workerId) {
        $servName = self::getProperty('servName');
        self::setProcessTitle($servName.'-Worker');
        self::setWorketPid($serv->worker_pid);

        //最后一个worker处理启动定时器
        $conf = self::getProperty('conf');
        if ($workerId == $conf['server_conf']['worker_num'] - 1) {
            //启动定时器任务
            echo "timerTaskManager\r\n";

            $timerManager = self::getTimerTaskManager();
            $timerManager->stopTimerTasks();
            $timerManager->startTimerTasks();
        }

        self::instance()->eventFire(__FUNCTION__);
        echo "Worker Start:[{$workerId}]...\r\n";

    }


    /**
     * 当worker进程停止[事件]
     * @param object $serv swoole_server对象
     * @param int $workerId 从0-$worker_num之间的数字
     */
    public static function onSwooleWorkerStop($serv, $workerId) {
        self::instance()->eventFire(__FUNCTION__);
        echo "Worker Stop:[{$workerId}]...\r\n";

    }



    /**
     * 当有新的连接进入时[事件]
     * @param object $serv swoole_server对象
     * @param mixed $fd 连接的文件描述符,发送数据/关闭连接时需要此参数
     * @param int $fromId 来自那个Reactor线程
     */
    public static function onSwooleConnect($serv, $fd, $fromId) {
        self::instance()->eventFire(__FUNCTION__);
        echo "new Connect:[{$fromId}]...\r\n";

    }



    /**
     * 当有http请求时[事件]
     * @param object $request 请求对象
     * @param object $response 响应对象
     *
     * @return bool
     */
    public static function onSwooleRequest($request, $response) {
        self::instance()->eventFire(__FUNCTION__);
        echo "on Request...\r\n";

        //不解析静态资源
        if ($request->server['request_uri'] == '/favicon.ico' || $request->server['path_info'] == '/favicon.ico') {
            $response->end();
            return false;
        }elseif (preg_match('/(.css|.js|.gif|.png|.jpg|.jpeg|.ttf|.woff|.ico)$/i', $request->server['request_uri']) === 1) {
            $response->end();
            return false;
        }

        $_REQUEST = $_SESSION = $_COOKIE = $_FILES = $_POST = $_SERVER = $_GET = [];
        //具体处理请求,留给子类去处理

        return true;
    }


    /**
     * 当客户端连接关闭时[事件]
     * @param object $serv swoole_server对象
     * @param mixed $fd 连接的文件描述符,发送数据/关闭连接时需要此参数
     * @param int $fromId 来自那个Reactor线程
     */
    public static function onSwooleClose($serv, $fd, $fromId) {
        self::instance()->eventFire(__FUNCTION__);
        echo "on Close:[{$fromId}]...\r\n";

    }


    /**
     * 当任务被调用时[事件]
     * @param object $serv swoole_server对象
     * @param int $taskId 任务ID
     * @param int $fromId 来自那个Reactor线程
     * @param array $taskData 任务数据
     */
    public static function onSwooleTask($serv, $taskId, $fromId, $taskData) {
        $servName = self::getProperty('servName');
        self::setProcessTitle($servName.'-Tasker');
        self::instance()->eventFire(__FUNCTION__);
        //echo "on Task...\r\n";

        //检查任务类型
        if(is_array($taskData) && isset($taskData['type'])) {
            switch ($taskData['type']) {
                case ServerConst::SERVER_TASK_TIMER : //定时任务
                    $callback = $taskData['message']['callback'] ?? '';
                    $params = $taskData['message']['params'] ?? [];
                    if(is_array($callback) && !is_callable($callback[0])) {
                        $obj = new $callback[0];
                        $callback[0] = $obj;
                    }
                    call_user_func_array($callback, $params);
                    break;
                case '' :default :
                    break;
            }
        }

        unset($taskData);

        return '1';
    }


    /**
     * 当任务完成时[事件]
     * @param object $serv swoole_server对象
     * @param int $taskId 任务ID
     * @param array $taskData 任务数据
     */
    public static function onSwooleFinish($serv, $taskId, $taskData) {
        self::instance()->eventFire(__FUNCTION__);
        //echo "on Finish...\r\n";

    }


    /**
     * 当收到管道消息时[事件]
     * @param $serv
     * @param $fromWorkerId
     * @param $message
     */
    public static function onSwoolePipeMessage($serv, $fromWorkerId, $message) {
        self::instance()->eventFire(__FUNCTION__);
        echo "on PipeMessage...\r\n";

    }



    /**
     * 当worker进程发生异常时[事件]
     * @param $serv
     * @param $workerId
     * @param $workerPid
     * @param $exitCode
     */
    public static function onSwooleWorkerError($serv, $workerId, $workerPid, $exitCode) {
        self::instance()->eventFire(__FUNCTION__);
        echo "on WorkerError...\r\n";
        echo "workerId[$workerId] workerPid[$workerPid] exitCode[$exitCode]\r\n";
        die;
    }





}