<?php

/**
 * Test function where phan infers the existence of a key of type PHP_INT_MAX.
 */
$a = [ PHP_INT_MAX=>PHP_INT_MIN ];
if (random_int(0,1) === 0) {
    $a = null;
}
$result = $a;
