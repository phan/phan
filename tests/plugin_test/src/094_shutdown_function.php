<?php

register_shutdown_function('notafunction');  // Should emit PhanUndeclaredFunctionInCallable
register_shutdown_function(['MissingClass', 'notafunction']);  // Should emit PhanUndeclaredClassInCallable
$array = ['stdClass', 'notafunction2'];
register_shutdown_function($array);  // Should emit PhanUndeclaredStaticMethodInCallable
