<?php
function test(?string $x, $objOrClass) {
    if (class_exists($x)) {
        '@phan-debug-var $x';
        echo "$x exists\n";
    }
    if (method_exists($objOrClass, 'foo')) {
        '@phan-debug-var $objOrClass';
        call_user_func([$objOrClass, 'foo']);
    }
    if (class_exists('123')) {
        echo "This shouldn't happen\n";
    }
    if (class_exists('OCI-lob')) {  // known exception to valid class FQSENs
        echo "What?\n";
    }
    if (class_exists('OCI-lobbbb')) {
        echo "What?\n";
    }
    if (class_exists(SomeArbitraryClass::class)) {  // TODO: Make ::class a different issue type from PhanUndeclaredClassConstant since the expression won't throw.
        echo "SomeArbitraryClass\n";
    }
}
