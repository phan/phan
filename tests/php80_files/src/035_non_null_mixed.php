<?php
function decode_or_default(string $json) {
    return json_decode($json) ?? 'default';
}
var_export(decode_or_default('null'));
