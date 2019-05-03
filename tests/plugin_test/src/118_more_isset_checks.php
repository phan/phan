<?php
function example118(?string $arg) {
    echo isset($arg) ? $arg : 'default';
    echo !is_null($arg) ? $arg : 'default';
    echo ($arg !== null) ? $arg : 'default';
    echo (null !== $arg) ? $arg : 'default';
}
