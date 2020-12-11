<?php
function decode_or_default(string $json) {
    return json_decode($json) ?? 'default';
}
