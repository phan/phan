<?php
call_user_func(function () {
    $o = new ArrayObject([]);
    $o->{$o}();
    var_export($o->{$o}());
    $o::{$o}();
    var_export($o::{$o}());
    ast\Node::{$o}();
    call_user_func([$o, $o]);
    call_user_func(['Closure', $o]);
});
