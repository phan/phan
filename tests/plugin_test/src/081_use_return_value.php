<?php
sprintf("%s is not used\n", 'return value');
count([]);
-count([]);  // should also warn
try {
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
}
