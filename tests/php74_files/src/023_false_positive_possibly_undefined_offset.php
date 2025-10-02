<?php

declare(strict_types=1);

$uri = (array) \parse_url('https://google.com');

$uri['host'] ??= '';
$uri['scheme'] ??= '';
'@phan-debug-var $uri';

$hostname = $uri['scheme'] === ''
    ? $uri['host']
    : "{$uri['scheme']}://{$uri['host']}";

var_dump($hostname); // string(18) "https://google.com"
