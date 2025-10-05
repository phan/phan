<?php

// Test for issue #4854: Type condition lost when intermediary variable is used

abstract class IThing {}

class Foo extends IThing {
    public function compare($x): bool {
        return true;
    }
}

class Bar extends IThing {
    public function compare($x): bool {
        return false;
    }
}

class Quux extends IThing {
    public function nah($x): bool {
        return true;
    }
}

// This works fine - direct instanceof in condition
function compare1(IThing $a, IThing $b): bool {
    if ($a instanceof Foo || $a instanceof Bar) {
        return $a->compare($b);  // No error - type is correctly narrowed
    }
    return false;
}

// This previously failed but should work with fix
function compare2(IThing $a, IThing $b): bool {
    $aHasCompare = ($a instanceof Foo || $a instanceof Bar);
    if ($aHasCompare) {
        return $a->compare($b);  // Should NOT error - type should be narrowed from boolean variable
    }
    return false;
}

// Test with more complex condition
function compare3(IThing $a, IThing $b): bool {
    $aIsValid = $a instanceof Foo || $a instanceof Bar || $a instanceof Quux;
    if ($aIsValid) {
        return $a->nah($b);  // Should NOT error - Quux has nah method
    }
    return false;
}

// Test that wrong method still errors
function compare4(IThing $a, IThing $b): bool {
    $aHasCompare = ($a instanceof Foo || $a instanceof Bar);
    if ($aHasCompare) {
        return $a->nah($b);  // SHOULD error - Foo/Bar don't have nah method
    }
    return false;
}

// Test with &&
function compare5(IThing $a, IThing $b): bool {
    $bothValid = ($a instanceof Foo && $b instanceof Bar);
    if ($bothValid) {
        return $a->compare($b);  // Should NOT error
    }
    return false;
}
