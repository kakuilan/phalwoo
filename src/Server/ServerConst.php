<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/8/12
 * Time: 20:59
 * Desc: -SWOOLE服务器常量定义
 */


namespace Lkk\Phalwoo\Server;

class ServerConst {

    //系统任务类型-定时任务
    const SERVER_TASK_TIMER                 = 500;

    //系统任务类型-工作流任务-session数据入库
    const SERVER_TASK_WORKFLOW_SESSION      = 501;

    //异步模式
    const MODE_ASYNC                        = 1;
    //同步模式
    const MODE_SYNC                         = 2;


    const SECOND_HOUR                       = 3600;             // 1小时的秒数
    const SECOND_DAY                        = 86400;            // 1天的秒数
    const SECOND_MONTH                      = 2592000;          // 1个月的秒数

    const ERR_SUCCESS                                = 0;               // 成功
    const ERR_MYSQL_TIMEOUT                          = -10;             // 数据库超时
    const ERR_MYSQL_QUERY_FAILED                     = -11;             // 查询失败
    const ERR_MYSQL_CONNECT_FAILED                   = -12;             // 连接失败

    const ERR_REDIS_CONNECT_FAILED                   = -20;             // Redis连接失败
    const ERR_REDIS_ERROR                            = -21;             // Redis请求失败
    const ERR_REDIS_TIMEOUT                          = -22;             // Redis超时

    const ERR_HTTP_CONN_CLOSE                        = -25;             // Http连接已关闭
    const ERR_HTTP_TIMEOUT                           = -26;             // Http请求超时


}