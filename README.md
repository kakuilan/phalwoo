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
