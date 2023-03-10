<?php
require_once "../vendor/autoload.php";

$obj = new \Rannk\SteamAppinfoPhp\Games();

//$result = $obj->getAppids();
//print_r($result);

$obj->hasCache();
print_r($obj->gameDetail(239140));

