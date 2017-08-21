<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/12
 * Time: 21:01
 * Desc: -SWOOLE服务器
 */


namespace Lkk\Phalwoo\Server;

use Lkk\LkkService;
use Lkk\Helpers\ValidateHelper;


class SwooleServer extends LkkService {


    public $conf; //服务配置
    private $server; //swoole_server对象
    private $events; //swoole_server事件
    private $requests; //请求资源
    private $splqueue; //标准库队列,非持久化工作
    private $redqueue; //redis持久化队列

    private $servName; //服务名
    private $listenIP; //监听IP
    private $listenPort; //监听端口

    //定时任务管理器
    private $timerTaskManager;

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


    /**
     * 构造函数
     * SwooleServer constructor.
     * @param array $vars
     */
    public function __construct(array $vars = []) {
        parent::__construct($vars);

    }


    /**
     * 获取SWOOLE服务
     * @return mixed
     */
    public static function getServer() {
        return (is_null(self::$instance) || !is_object(self::$instance)) ? null : self::$instance->server;
    }


    /**
     * 获取定时任务管理器
     * @return mixed
     */
    public static function getTimerTaskManager() {
        return (is_null(self::$instance) || !is_object(self::$instance)) ? null : self::$instance->timerTaskManager;
    }


    /**
     * 设置内置队列对象
     */
    private function setSplQueue() {
        $this->splqueue = new \SplQueue();
        //设置迭代后数据删除
        $this->splqueue->setIteratorMode(\SplDoublyLinkedList::IT_MODE_FIFO | \SplDoublyLinkedList::IT_MODE_DELETE);
    }


    /**
     * 获取内置队列对象
     * @return mixed
     */
    public static function getSplQueue() {
        return (is_null(self::$instance) || !is_object(self::$instance)) ? null : self::$instance->splqueue;
    }


    /**
     * 设置redis队列对象
     */
    protected function setRedQueue() {
        //默认使用工作流队列,子类可自行更改
        $this->redqueue = RedisQueue::getQueueObject(RedisQueue::APP_WORKFLOW_QUEUE_NAME, []);
    }


    /**
     * 获取redis队列对象
     * @return mixed
     */
    public static function getRedQueue() {
        return (is_null(self::$instance) || !is_object(self::$instance)) ? null : self::$instance->redqueue;
    }


    /**
     * 获取请求的UUID
     * @return string
     */
    public static function getRequestUuid() {
        $arr = array_merge($_GET, $_COOKIE, $_SERVER);
        sort($arr);
        $res = isset($_SERVER['REQUEST_TIME_FLOAT']) ?
            (sprintf('%.0f', $_SERVER['REQUEST_TIME_FLOAT'] * 1000000) .crc32(md5(serialize($arr))))
            : md5(serialize($arr));
        return $res;
    }


    /**
     * 设置request请求对象
     * @param $request
     */
    protected function setSwooleRequest($request) {
        $requestId = self::getRequestUuid();
        $this->requests[$requestId] = $request;
    }


    /**
     * 取消request请求对象
     * @param string $requestId
     */
    protected function unsetSwooleRequest($requestId='') {
        if(empty($requestId)) $requestId = self::getRequestUuid();
        unset($this->requests[$requestId]);
    }


    /**
     * 获取request对象
     * @param string $requestId
     *
     * @return null
     */
    protected static function getSwooleRequest($requestId='') {
        $res = null;
        if(is_object(self::$instance) && !empty(self::$instance)) {
            if(empty($requestId)) $requestId = self::getRequestUuid();
            $res = isset(self::$instance->requests[$requestId]) ? self::$instance->requests[$requestId] : null;
        }

        return $res;
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
        if (php_sapi_name() != 'cli') {
            exit("only run in command line mode \r\n");
        }

        //销毁旧对象
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
            @cli_set_process_title($title);
        } // Need proctitle when php<=5.5 .
        else {
            @swoole_set_process_name($title);
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
     * 绑定事件
     */
    public function bindEvents() {
        $this->server->on('Start', [$this, 'onStart']);
        $this->server->on('Shutdown', [$this, 'onShutdown']);
        $this->server->on('WorkerStart', [$this, 'onWorkerStart']);
        $this->server->on('WorkerStop', [$this, 'onWorkerStop']);
        $this->server->on('Connect', [$this, 'onConnect']);
        $this->server->on('Request', [$this, 'onRequest']);
        $this->server->on('Close', [$this, 'onClose']);
        $this->server->on('Task', [$this, 'onTask']);
        $this->server->on('Finish', [$this, 'onFinish']);
        $this->server->on('PipeMessage', [$this, 'onPipeMessage']);
        $this->server->on('WorkerError', [$this, 'onWorkerError']);
        $this->server->on('ManagerStart', [$this, 'onManagerStart']);
        $this->server->on('ManagerStop', [$this, 'onManagerStop']);

        return $this;
    }


    /**
     * 当服务器启动时[事件]
     * @param object $serv swoole_server对象
     *
     * @return $this
     */
    public function onStart($serv) {
        self::setProcessTitle($this->servName.'-Master');
        self::setMasterPid($serv->master_pid, $serv->manager_pid);

        $this->setSplQueue();
        $this->setRedQueue();

        $this->eventFire(__FUNCTION__);
        echo "Master Start...\r\n";

        return $this;
    }


    /**
     * 当服务器关闭时[事件]
     * @param object $serv swoole_server对象
     *
     * @return $this
     */
    public function onShutdown($serv) {
        $this->eventFire(__FUNCTION__);
        echo "Master Shutdown...\r\n";

        return $this;
    }


    /**
     * 当管理进程启动时[事件]
     * @param object $serv swoole_server对象
     */
    public function onManagerStart($serv) {
        self::setProcessTitle($this->servName.'-Manager');

        $this->eventFire(__FUNCTION__);
        echo "Manager Start...\r\n";

        return $this;
    }


    /**
     * 当管理进程停止时[事件]
     * @param object $serv swoole_server对象
     *
     * @return $this
     */
    public function onManagerStop($serv) {
        $this->eventFire(__FUNCTION__);
        echo "Manager Stop...\r\n";

        return $this;
    }


    /**
     * 当worker进程启动[事件]
     * @param object $serv swoole_server对象
     * @param int $workerId 从0-$worker_num之间的数字
     *
     * @return $this
     */
    public function onWorkerStart($serv, $workerId) {
        self::setProcessTitle($this->servName.'-Worker');
        self::setWorketPid($serv->worker_pid);

        //最后一个worker处理启动定时器
        if ($workerId == $this->conf['server_conf']['worker_num'] - 1) {
            //启动定时器任务
            $this->timerTaskManager = new TimerTaskManager(['timerTasks'=>$this->conf['timer_tasks']]);
        }

        $this->eventFire(__FUNCTION__);
        echo "Worker Start:[{$workerId}]...\r\n";

        return $this;
    }


    /**
     * 当worker进程停止[事件]
     * @param object $serv swoole_server对象
     * @param int $workerId 从0-$worker_num之间的数字
     *
     * @return $this
     */
    public function onWorkerStop($serv, $workerId) {
        $this->eventFire(__FUNCTION__);
        echo "Worker Stop:[{$workerId}]...\r\n";

        return $this;
    }


    /**
     * 当有新的连接进入时[事件]
     * @param object $serv swoole_server对象
     * @param mixed $fd 连接的文件描述符,发送数据/关闭连接时需要此参数
     * @param int $fromId 来自那个Reactor线程
     *
     * @return $this
     */
    public function onConnect($serv, $fd, $fromId) {
        $this->eventFire(__FUNCTION__);
        echo "new Connect:[{$fromId}]...\r\n";

        return $this;
    }


    /**
     * 当有http请求时[事件]
     * @param object $request 请求对象
     * @param object $response 响应对象
     *
     * @return $this
     */
    public function onRequest($request, $response) {
        $this->eventFire(__FUNCTION__);
        echo "on Request...\r\n";

        //TODO 注册捕获错误函数
        //register_shutdown_function();

        //不解析静态资源
        if ($request->server['request_uri'] == '/favicon.ico' || $request->server['path_info'] == '/favicon.ico') {
            return $response->end();
        }elseif (preg_match('/(.css|.js|.gif|.png|.jpg|.jpeg|.ttf|.woff|.ico)$/i', $request->server['request_uri']) === 1) {
            return $response->end();
        }

        $this->setGlobal($request);
        self::setSwooleRequest($request);

        //处理请求
        //TODO 留给子类具体去处理
        //ob_start();
        /*try {
            $resStr = date('Y-m-d H:i:s') . ' Hello World.';
        } catch (\Exception $e) {
            $resStr = $e->getMessage();
        }
        //$result = ob_get_contents();
        //ob_end_clean();
        $response->end($resStr);
        self::unsetSwooleRequest($requestId);*/

        return $this;
    }


    /**
     * 当http响应之后[供子类调用]
     * @param $request
     * @param $response
     */
    public function afterResponse($request, $response) {
        $requestId = self::getRequestUuid();
        self::unsetSwooleRequest($requestId);

        $this->unsetGlobal();
        unset($request);
        unset($response);
    }


    /**
     * 当客户端连接关闭时[事件]
     * @param object $serv swoole_server对象
     * @param mixed $fd 连接的文件描述符,发送数据/关闭连接时需要此参数
     * @param int $fromId 来自那个Reactor线程
     *
     * @return $this
     */
    public function onClose($serv, $fd, $fromId) {
        $this->eventFire(__FUNCTION__);
        echo "on Close:[{$fromId}]...\r\n";

        return $this;
    }


    /**
     * 当任务被调用时[事件]
     * @param object $serv swoole_server对象
     * @param int $taskId 任务ID
     * @param int $fromId 来自那个Reactor线程
     * @param array $taskData 任务数据
     *
     * @return $this
     */
    public function onTask($serv, $taskId, $fromId, $taskData) {
        self::setProcessTitle($this->servName.'-Tasker');
        $this->eventFire(__FUNCTION__);
        echo "on Task...\r\n";

        //检查任务类型
        if(is_array($taskData) && isset($taskData['type'])) {
            switch ($taskData['type']) {
                case '' :default :
                    break;
                case ServerConst::SERVER_TASK_TIMER : //定时任务
                    call_user_func_array($taskData['message']['callback'], $taskData['message']['params']);
                    break;
            }
        }

        return $this;
    }


    /**
     * 当任务完成时[事件]
     * @param object $serv swoole_server对象
     * @param int $taskId 任务ID
     * @param array $taskData 任务数据
     *
     * @return $this
     */
    public function onFinish($serv, $taskId, $taskData) {
        $this->eventFire(__FUNCTION__);
        echo "on Finish...\r\n";

        return $this;
    }


    /**
     * 当收到管道消息时[事件]
     * @param $serv
     * @param $fromWorkerId
     * @param $message
     *
     * @return $this
     */
    public function onPipeMessage($serv, $fromWorkerId, $message) {
        $this->eventFire(__FUNCTION__);
        echo "on PipeMessage...\r\n";

        return $this;
    }


    /**
     * 当worker进程发生异常时[事件]
     * @param $serv
     * @param $workerId
     * @param $workerPid
     * @param $exitCode
     *
     * @return $this
     */
    public function onWorkerError($serv, $workerId, $workerPid, $exitCode) {
        $this->eventFire(__FUNCTION__);
        echo "on WorkerError...\r\n";

        return $this;
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
                $timeout = 5;
                $startTime = time();
                while (1) {
                    $masterIsAlive = $masterPid && posix_kill($masterPid, 0);
                    if ($masterIsAlive) {
                        if (time() - $startTime >= $timeout) {
                            echo("Service $this->servName stop fail\r\n");
                            exit;
                        }
                        // Waiting amoment.
                        usleep(10000);
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
                $timeout = 5;
                $startTime = time();
                while (1) {
                    $masterIsAlive = $masterPid && posix_kill($masterPid, 0);
                    if ($masterIsAlive) {
                        if (time() - $startTime >= $timeout) {
                            echo("Service $this->servName stop fail\r\n");
                            exit;
                        }
                        // Waiting amoment.
                        usleep(10000);
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
        $this->server->start();

        echo("Service $this->servName start success\r\n");

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
     * 设置全局变量[将原始请求信息转换到PHP超全局变量中]
     * @param mixed $request request对象
     */
    public function setGlobal($request) {
        $_REQUEST = $_SESSION = $_COOKIE = $_FILES = $_POST = $_SERVER = $_GET = [];

        if (isset($request->get)) $_GET = $request->get;
        if (isset($request->post)) $_POST = $request->post;
        if (isset($request->files)) $_FILES = $request->files;
        if (isset($request->cookie)) $_COOKIE = $request->cookie;
        if (isset($request->server)) $_SERVER = $request->server;

        //构造url请求路径,phalcon获取到$_GET['_url']时会定向到对应的路径，否则请求路径为'/'
        $_GET['_url'] = $request->server['request_uri'];

        $_REQUEST = array_merge($_GET, $_POST);

        //todo: necessary?
        foreach ($_SERVER as $key => $value) {
            unset($_SERVER[$key]);
            $_SERVER[strtoupper($key)] = $value;
        }
        $_REQUEST = array_merge($_GET, $_POST, $_COOKIE);
        $_SERVER['REQUEST_URI'] = $request->server['request_uri'];

        //将HTTP头信息赋值给$_SERVER超全局变量
        foreach ($request->header as $key => $value) {
            $_key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
            $_SERVER[$_key] = $value;
        }
        $_SERVER['REMOTE_ADDR'] = $request->server['remote_addr'];

        // swoole fix 初始化一些变量, 下面这些变量在进入真实流程时是无效的
        $_SERVER['PHP_SELF']        = '/index.php';
        $_SERVER['SCRIPT_NAME']     = '/index.php';
        $_SERVER['SCRIPT_FILENAME'] = '/index.php';
        $_SERVER['SERVER_ADDR']     = '127.0.0.1';
        $_SERVER['SERVER_NAME']     = 'localhost';

        //TODO
        //$_SESSION = $this->load($sessid);
    }


    /**
     * 取消全局变量[重置为空]
     */
    public function unsetGlobal() {
        $_REQUEST = $_SESSION = $_COOKIE = $_FILES = $_POST = $_SERVER = $_GET = [];
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



}