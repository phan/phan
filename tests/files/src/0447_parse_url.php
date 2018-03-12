<?php declare(strict_types=1);

$x = parse_url('http://example.com:8080/path?key=value');
var_export($x);
echo strlen($x['port']);
echo count($x['host']);
echo $x['Host'];
