<?php
require_once "../vendor/autoload.php";

$obj = new \Rannk\SteamAppinfoPhp\Games();

//$result = $obj->getAppids();
//print_r($result);

print_r($obj->gameDetail(2208920));