<?php

try {
    $GLOBALS['somefunc']();
} catch ( RuntimeException | LogicException $unused ) {
} catch ( Throwable $_ ) {
}
