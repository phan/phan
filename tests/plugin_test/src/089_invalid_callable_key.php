<?php
function invoke_callable(string $class, string $method) {
    call_user_func([0=>$class, 2=>$method]);  // PhanTypeInvalidCallable emitted as a side effect of UseReturnValuePlugin
}
invoke_callable('a', 'b');
