<?php
/**
 * Created by JetBrains PhpStorm.
 * User: zeus
 * Date: 10/27/13
 * Time: 1:02 PM
 *
 * possible params
 * url=<url> - required
 * limit=<limit>
 * level=<level>
 */

require_once 'uwc.php';

$params = $_SERVER['argv'];
array_shift($params);
$get = array();
foreach($params as $param) {
    list($key, $value) = explode('=', $param);
    $get[$key] = $value;
}

$start = microtime(true);

if(isset($get['url'])){
    $page_url = $get['url'];
} else {
    die('No url');
}
$p = new Parser($page_url);
echo (microtime(true) - $start).PHP_EOL;
echo xdebug_memory_usage().PHP_EOL;
if(isset($get['level']) && $level=intval($get['level'])){
    $p->setLevel($get['level']);
}
if(isset($get['limit']) && $limit=intval($get['limit'])){
    $p->setLimit($limit);
}
print_r($p->run());
echo xdebug_memory_usage().PHP_EOL;
echo microtime(true) - $start;