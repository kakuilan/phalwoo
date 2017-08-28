<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/13
 * Time: 17:12
 * Desc: -服务配置
 */


return [
    'server_name' => 'Kserver',
    'server_vers' => '0.0.1',
    'pid_dir' => '/tmp/',

    //是否热更新服务代码,需inotify扩展
    'server_reload' => false,

    //http服务监听
    'http_server' => [
        'host' => '0.0.0.0',
        'port' => 8080, //6666
    ],


    //服务配置
    'server_conf' => [
        //守护进程化
        'daemonize' => 0,
        //线程数,=CPU核数
        'reactor_num' => 2,
        //worker进程数,为CPU的1-4倍
        'worker_num' => 4,
        //服务最大允许的连接数
        'max_conn' => 10240,
        //task进程的数量
        'task_worker_num' => 5,
        //task进程的最大任务数,0不限制
        'task_max_request' => 1024,
        //task的数据临时目录
        'task_tmpdir' => '/tmp',
        //数据包分发策略
        'dispatch_mode' => 2,
        //Listen队列长度
        'backlog' => 128,
        //指定swoole错误日志文件
        'log_file' => '/tmp/swoole.log',
        //日志级别
        'log_level' => 0,
        //启用心跳检测,每隔N秒轮循一次
        'heartbeat_check_interval' => 5,
        //表示连接最大允许空闲的时间,需要比heartbeat_check_interval大
        'heartbeat_idle_time' => 10,

        //打开包长检测
        'open_length_check' => true,
        //包长度值的类型
        'package_length_type' => 'N',
        //最大数据包尺寸
        'package_max_length' => 2097152, //2M
        'package_length_offset' => 0,
        'package_body_offset' => 4,
        //管道通信的内存缓存区长度
        'pipe_buffer_size' => 33554432, //32M
        //发送缓存区尺寸
        'buffer_output_size' => 3145728, //3M

        //立即发往客户端连接,提升响应速度
        'open_tcp_nodelay' => 1,
        //启用CPU亲和性设置
        'open_cpu_affinity' => 1,
        //设置端口重用
        'enable_reuse_port' => 1,
    ],

    //定时任务
    'timer_tasks' => [
        [
            /*'type' => \Lkk\Phalwoo\Server\ServerConst::SERVER_TASK_TIMER,
            'message' => [
                'title' => 'timerTest',
                'callback' => ['\Tests\Server\Task','dumpTest'],
                'params' => ['timerTest-hah'],
            ]*/
        ],
        [
            'type' => \Lkk\Phalwoo\Server\ServerConst::SERVER_TASK_TIMER,
            'message' => [
                'title' => 'sessionTest',
                'callback' => ['\Tests\Server\Task','sessionTest'],
                'params' => [],
            ]
        ],
    ],

];