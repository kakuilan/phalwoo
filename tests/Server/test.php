<?php
/**
 * Created by PhpStorm.
 * User: kakuilan@163.com
 * Date: 17-9-11
 * Time: 下午7:16
 * Desc:
 */


define('DS', str_replace('\\', '/', DIRECTORY_SEPARATOR));
define('PS', PATH_SEPARATOR);

$loader = require __DIR__ .'/../../vendor/autoload.php';
$loader->addPsr4('Tests\\', dirname(__DIR__));

use Hprose\Promise;

function get($url) {
    $dns_lookup = Promise\promisify('swoole_async_dns_lookup');
    $url = parse_url($url);
    //list($host, $ip) = (yield $dns_lookup($url['host']));

    yield $dns_lookup($url['host']);
}


$urls = [
    'http://baidu.com',
    'http://163.com',
    'http://ifeng.com',
];

foreach ($urls as $k=>$url) {
    //$arr = Promise\co(get($url));
}


Promise\co(function (){
    $result = Promise\co(get('http://baidu.com'));
    yield Promise\all($result);
    var_dump($result);
});


