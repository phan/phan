<?php
// This is wrong, checking that phan has correct results.
echo strlen(call_user_func('intdiv', 2, []));
// Check that phan doesn't crash
$args = [2,3];
echo strlen(call_user_func('intdiv', ...$args));
echo strlen(call_user_func_array('intdiv', [2, new SimpleXMLElement('<a></a>')]));
echo strlen(call_user_func_array('intdiv', $args));
