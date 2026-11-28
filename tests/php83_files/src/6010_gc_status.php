<?php

// gc_status() has more keys since PHP 8.3.
function gcStatus(): array {
    $status = gc_status();
    '@phan-debug-var $status';
    return [$status['running'], $status['application_time'], $status['buffer_size'], $status['runs'], $status['missing']];
}
