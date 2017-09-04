<?php
/**
 * Created by PhpStorm.
 * User: kakuilan@163.com
 * Date: 17-9-4
 * Time: 上午11:33
 * Desc:
 */


namespace Tests\Server;

class TestServer {

    public $_onStart;
    public $_onShutdown;
    public $_onManagerStart;
    public $_onManagerStop;
    public $_onWorkerStart;
    public $_onWorkerStop;
    public $_onConnect;
    public $_onRequest;
    public $_onClose;
    public $_onTask;
    public $_onFinish;

    public $conf;
    public $server;

    public function __construct() {

    }


    /**
     * 设置配置
     * @param array $conf
     */
    public function setConf(array $conf) {
        $this->conf = $conf;

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
        $servCnf['daemonize'] = 0;
        $this->server->set($servCnf);

        return $this;
    }


    /**
     * 启动服务
     * @return $this
     */
    public function startServer() {
        $this->bindEvents();

        echo("Service start success\r\n");
        $this->server->start();

        return $this;
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
        echo "Master Start...\r\n";

        $this->_onStart = 1;

        return $this;
    }


    /**
     * 当服务器关闭时[事件]
     * @param object $serv swoole_server对象
     *
     * @return $this
     */
    public function onShutdown($serv) {
        echo "Master Shutdown...\r\n";

        return $this;
    }


    /**
     * 当管理进程启动时[事件]
     * @param object $serv swoole_server对象
     */
    public function onManagerStart($serv) {
        echo "Manager Start...\r\n";

        $this->_onManagerStart = 2;
        var_dump($this);


        return $this;
    }


    /**
     * 当管理进程停止时[事件]
     * @param object $serv swoole_server对象
     *
     * @return $this
     */
    public function onManagerStop($serv) {
        echo "Manager Stop...\r\n";

        $this->_onManagerStop = 3;
        var_dump($this);

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
        echo "Worker Start:[{$workerId}]...\r\n";

        $this->_onWorkerStart = 4;
        var_dump($this);

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
        echo "Worker Stop:[{$workerId}]...\r\n";

        $this->_onWorkerStop = 5;
        var_dump($this);

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
        echo "new Connect:[{$fromId}]...\r\n";

        $this->_onConnect = 6;
        var_dump($this);

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
        echo "on Request...\r\n";

        $this->_onRequest = 7;
        var_dump($this);

        return $this;
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
        echo "on Close:[{$fromId}]...\r\n";

        $this->_onClose = 8;
        var_dump($this);

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
        echo "on Task...\r\n";

        $this->_onTask = 9;

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
        echo "on Finish...\r\n";

        $this->_onFinish = 10;

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
        echo "on WorkerError...\r\n";

        return $this;
    }



}