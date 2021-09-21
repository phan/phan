<?php
function doStuff() {
    try {
        return true;
    } catch ( \LogicException $e2 ) {
        $t = 'logic';
    } catch ( \Exception $e ) {
        $t = 'exception';
    }
    echo $t;
    '@phan-debug-var $t, $e2';
}
