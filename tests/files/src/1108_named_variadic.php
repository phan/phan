<?php
function test36(int $arg) {
    var_export($arg);
}
call_user_func('test36', arg: 123);
sprintf("foo=%s\n", foo: 'test');
$c = Closure::fromCallable('test36');
$c->call(arg: 123);
