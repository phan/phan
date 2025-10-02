<?php
function decode_or_default(string $json) {
    return json_decode($json) ?? 'default';
}
$r = decode_or_default('null');
'@phan-debug-var $r';
