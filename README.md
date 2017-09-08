# phalwoo
swoole server frame for phalcon  
必须安装swoole和phalcon扩展

测试  
cd tests  
phpunit ServerTest.php  
或  
php Server/bin.php start

nginx配置  
``` bash
server {
    listen 80;
    root /home/wwwroot/default;
    server_name my.com;

    location / {
        try_files $uri $uri/ /index.php?_url=$uri&$args;
    }
    location ~ ^/(index)\.php(/|$) {
        proxy_set_header X-Real-IP  $remote_addr;
        proxy_set_header X-Forwarded-For $remote_addr;
        proxy_set_header Host $host;
        proxy_pass http://127.0.0.1:6666;
    }
}
```

部署时去掉:  
phalcon/ide-stubs
eaglewu/swoole-ide-helper  
react/promise  


$ret->send();
$response->end($ret->getContent());

session前缀_PHCR _PHCOOKIE_ 
压测性能不行,找原因:  
`1 redis写入问题  

TODO:  
定时器待优化,会导致worker err  
Cmponent 组件
log eventsManager  
日志切割  
redis长连接  
db长连接   
大量连接时session redis超时错误  


连接池参考Promise   
https://github.com/reactphp/promise  
https://github.com/guzzle/promises  
cat-sys/cat-core  
Hprose\Promise  
Prophecy  
将cookie里面写_PHCOOKIE_拆出来  


-----------------------
cron定时器基本格式 :
*　　*　　*　　*　　*　　
分　时　日　月　周　命令
第1列表示分钟1～59 每分钟用*或者 */1表示
第2列表示小时1～23（0表示0点）
第3列表示日期1～31
第4列表示月份1～12
第5列标识号星期0～6（0表示星期天）

------------------
abnormal exit, status=0, signal=11  
signal=11 表示产生了 core dump，你需要使用 gdb 跟踪  

https://stackoverflow.com/questions/41575854/php-generator-catch-exception-and-yield
https://stackoverflow.com/questions/16596281/is-this-implementation-a-fair-example-of-a-promise-in-php
https://stackoverflow.com/questions/21517176/return-synchronously-when-a-react-promise-is-resolved

