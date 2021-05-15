<?php

class Checker8 {
    /** @return no-return */
    public function customExit() {
        echo "Stack overflow error: Check stack overflow\n";
        exit(1);
    }
}
function test8(?string $unknown) {
    $exitAndLog = function (string $message): never {
        exit($message);
    };

    is_string($unknown) || $exitAndLog('expected unknown to be a string');
    '@phan-debug-var $unknown';
    $custom = new Checker8();
    $custom->customExit();
    echo "Done\n";  // unreachable
}
