<?php
/**
 * Created by JetBrains PhpStorm.
 * User: zeus
 * Date: 10/27/13
 * Time: 1:02 PM
 */
require_once 'uwc.php';

$start = microtime(true);

if(isset($_GET['url'])){
    $page_url = $_GET['url'];
} else {
    die('No url');
}
$p = new Parser($page_url);
if(isset($_GET['level']) && $level=intval($_GET['level'])){
    $p->setLevel($_GET['level']);
}
if(isset($_GET['limit']) && $limit=intval($_GET['limit'])){
    $p->setLimit($limit);
}
foreach($p->run() as $file => $selectors){
    echo "<div>$file (".count($selectors).")</div>";
    echo "<ol style='display: none'>";
    foreach($selectors as $selector) {
        echo "<li>$selector</li>";
    }
    echo "</ol>";
}
echo '<p>memory: '.xdebug_memory_usage().'</p>';
echo '<p>time: '.(microtime(true) - $start).' s</p>';