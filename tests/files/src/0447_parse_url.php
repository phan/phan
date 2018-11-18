<?php declare(strict_types=1);

$x = parse_url('http://example.com:8080/path?key=value');
var_export($x);
echo strlen($x['port']);
echo count($x['host']);
echo $x['Host'];
// Note that Phan has't implemented mapping components of parse_url to the specific union type yet.
// Should warn about being non-array, though
echo count(parse_url('http://example.com:8080/path?key=value', PHP_URL_HOST));
