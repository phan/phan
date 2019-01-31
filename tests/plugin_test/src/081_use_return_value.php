<?php
sprintf("%s is not used\n", 'return value');
count([]);
try {
} catch (Exception $e) {
    // Should warn - The message is usually used.
    // TODO: Make PhanPluginUseReturnValueInternalKnown also check for methods that override methods of Exception
    $e->getMessage();
}
