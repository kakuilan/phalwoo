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

$ret->send();
$response->end($ret->getContent());

session前缀_PHCR _PHCOOKIE_ 
压测性能不行,找原因:  
`1 redis写入问题  

TODO:  
Cmponent 组件
log eventsManager  
redis长连接  
db长连接   
获取内存地址  


连接池参考Promise   
cat-sys/cat-core  
Hprose\Promise  
Prophecy  


-----------------------
cron定时器基本格式 :
*　　*　　*　　*　　*　　
分　时　日　月　周　命令
第1列表示分钟1～59 每分钟用*或者 */1表示
第2列表示小时1～23（0表示0点）
第3列表示日期1～31
第4列表示月份1～12
第5列标识号星期0～6（0表示星期天）

            