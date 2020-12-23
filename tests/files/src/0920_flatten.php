<?php

/** @throws RuntimeException */
function maybeThrow920() {
    if ( rand() ) {
        throw new \RuntimeException;
    }
    return true;
}

function check920(int $flag) {
	try {
		maybeThrow920();
	} catch ( \RuntimeException $e ) {
        $flag = false;
        $excep = $e;
    } catch (Error $e) {
        $flag = 'error';
    }
    if (rand()) {
        '@phan-debug-var $excep, $flag';
        return [$flag, $excep ?? null]; // PhanCoalescingNeverUndefined Using $excep ?? null seems unnecessary - the expression appears to always be defined
    } else {
        '@phan-debug-var $e';
        return $e ?? null;
    }
}
check920();
