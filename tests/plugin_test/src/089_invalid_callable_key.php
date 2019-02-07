<?php
function invoke_callable(string $class, string $method) {
    call_user_func([0=>$class, 2=>$method]);
}
invoke_callable('a', 'b');
