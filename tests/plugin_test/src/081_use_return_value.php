<?php
sprintf("%s is not used\n", 'return value');
count([]);
-count([]);  // should also warn
try {  // should emit PhanPluginEmptyStatementTryBody
} catch (Exception $e) {
    // Should warn - The message is usually used.
    // TODO: Make PhanPluginUseReturnValueInternalKnown also check for methods that override methods of Exception
    $e->getMessage();
    // should also warn
    $e->getCode();
    @$e->getCode();
    @$e->getMissing();
    // should not warn
    var_export(-$e->getCode());
    // should warn
    -$e->getCode();
} finally {  // should emit PhanPluginEmptyStatementTryFinally
}
try {
    throw new RuntimeException();
} catch (Exception $e) {
    echo $e;
} finally // should emit PhanPluginEmptyStatementTryFinally
{
}
