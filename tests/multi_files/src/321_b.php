<?php
use \Foo\Api;
require '321.php';
$api = new Api\Api();
printf("%s\n", $api->getName());
