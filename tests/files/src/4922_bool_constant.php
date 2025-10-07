<?php

/**
 * Return a descriptive label based on the thread-safety build flag.
 */
function zts_mode(): string {
    return PHP_ZTS ? 'ts' : 'nts';
}

$result = PHP_DEBUG ? 'debug' : 'nodebug';
