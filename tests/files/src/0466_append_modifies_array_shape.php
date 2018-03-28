<?php
call_user_func(function() {
    $x = [];
    $x[] = $this->undefVar;  // Expected PhanUndeclaredVariable
    echo $x[0];
    $s = 'key';
    echo $x[$s];
    echo $x['key2'];
    echo strlen($x);
});
