<?php

/**
 * @return string
 * @throws InvalidArgumentException
 */
function testRedundantPHPDocCrashThrows(): string {
    if ( rand() > 0 ) {
        throw new InvalidArgumentException();
    }
    return 'foo';
}
testRedundantPHPDocCrashThrows();
