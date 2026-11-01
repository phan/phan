<?php
// Regression test for #5577: positive-int, negative-int and non-zero-int survive arithmetic when the sign of the result is known.

/** @return positive-int */
function getPositiveInt5940(): int {
    return $GLOBALS['p'];
}

/** @return negative-int */
function getNegativeInt5940(): int {
    return $GLOBALS['n'];
}

/** @return non-zero-int */
function getNonZeroInt5940(): int {
    return $GLOBALS['nz'];
}

/** @return int-range<0, 10> */
function getSmallInt5940(): int {
    return $GLOBALS['s'];
}

/** @param positive-int $p */
function takesPositive5940(int $p): void {
    var_export($p);
}

/** @param negative-int $n */
function takesNegative5940(int $n): void {
    var_export($n);
}

class Counter5940 {
    /** @var positive-int */
    public $count = 1;
}

( function () {
    $p = getPositiveInt5940();
    $x = $p - 1;
    '@phan-debug-var $x'; // int
    $x = $p - 0;
    '@phan-debug-var $x'; // positive-int
    $x = $p + 1;
    '@phan-debug-var $x'; // positive-int
    $p++;
    '@phan-debug-var $p'; // positive-int
    $p = getPositiveInt5940();
    $p--;
    '@phan-debug-var $p'; // int
    $y = -getPositiveInt5940();
    '@phan-debug-var $y'; // negative-int
    $z = getPositiveInt5940() + getPositiveInt5940();
    '@phan-debug-var $z'; // positive-int

    $n = getNegativeInt5940();
    $x = $n - 1;
    '@phan-debug-var $x'; // negative-int
    $x = $n + 0;
    '@phan-debug-var $x'; // negative-int
    $x = $n + 1;
    '@phan-debug-var $x'; // int
    $n--;
    '@phan-debug-var $n'; // negative-int
    $n = getNegativeInt5940();
    $n++;
    '@phan-debug-var $n'; // int
    $y = -getNegativeInt5940();
    '@phan-debug-var $y'; // positive-int
    $z = getNegativeInt5940() + getNegativeInt5940();
    '@phan-debug-var $z'; // negative-int

    // Multiplication
    $m = getPositiveInt5940() * getPositiveInt5940();
    '@phan-debug-var $m'; // positive-int
    $m = getNegativeInt5940() * getNegativeInt5940();
    '@phan-debug-var $m'; // positive-int
    $m = getPositiveInt5940() * getNegativeInt5940();
    '@phan-debug-var $m'; // negative-int
    $m = getNonZeroInt5940() * getNonZeroInt5940();
    '@phan-debug-var $m'; // non-zero-int
    $m = getNonZeroInt5940() * getPositiveInt5940();
    '@phan-debug-var $m'; // non-zero-int
    $m = getPositiveInt5940() * 0;
    '@phan-debug-var $m'; // int
    $m = getPositiveInt5940() * rand();
    '@phan-debug-var $m'; // int

    // Compound assignment
    $c = getPositiveInt5940();
    $c += 2;
    '@phan-debug-var $c'; // positive-int
    $c -= 1;
    '@phan-debug-var $c'; // int
    $c = getNegativeInt5940();
    $c -= getPositiveInt5940();
    '@phan-debug-var $c'; // negative-int
    $c *= getNegativeInt5940();
    '@phan-debug-var $c'; // positive-int

    // Operands that are not purely int keep the old behavior
    $f = getPositiveInt5940() + 1.5;
    '@phan-debug-var $f'; // float
    $u = getPositiveInt5940() + rand();
    '@phan-debug-var $u'; // int
    $r = getSmallInt5940() + 1;
    '@phan-debug-var $r'; // positive-int
    $r = getSmallInt5940() - 11;
    '@phan-debug-var $r'; // negative-int

    // Increment in a loop stays positive, and literals are not inferred for loop counters.
    $i = 0;
    while (rand() < 5) {
        $i++;
        '@phan-debug-var $i'; // positive-int
        echo $i;
    }
    '@phan-debug-var $i'; // 0|positive-int

    // Properties
    $counter = new Counter5940();
    $counter->count++;
    takesPositive5940($counter->count);
    $counter->count *= -1;
    takesPositive5940($counter->count);

    takesPositive5940(getPositiveInt5940() + 1);
    takesNegative5940(-getPositiveInt5940());
    takesPositive5940(getPositiveInt5940() - 1);
    takesNegative5940(getNegativeInt5940() + 1);
} )();
