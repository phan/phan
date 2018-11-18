<?php

call_user_func(function () {
    preg_match('/a./', 'analysis', $matches);
    var_export($matches);
    echo strlen($matches);
    preg_match_all('/a./', 'analysis', $matches);
    var_export($matches);
    echo strlen($matches);
    preg_match('/a(.)/', 'analysis', $matches, PREG_OFFSET_CAPTURE);
    var_export($matches);
    echo strlen($matches);
    preg_match_all('/a(.)/', 'analysis', $matches, PREG_OFFSET_CAPTURE);
    var_export($matches);
    echo strlen($matches);
    preg_match_all('/a(.)/', 'analysis', $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
    var_export($matches);
    echo strlen($matches);
});
