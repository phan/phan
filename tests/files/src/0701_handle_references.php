<?php

class C701 {
    /**
     * @return string|false
     */
    public static function some_method($other) {
        echo "Saw $other\n";
        return 'value';
    }
}

function trycatch_701(int $i, $other, &$a, &$b) {
    try {
        $a = 'x';
    } catch (Exception $_) {
        $a = null;
    }
    // Should not warn
    // Observed: PhanImpossibleCondition Impossible attempt to cast $a of type null to truthy
    if (!$a) {
        echo "Found invalid a\n";
    }
}

function trycatch_impossible_701(int $i, $other, &$a, &$b) {
    try {
        $a = 'x';
        if (rand() % 2 > 0) {
            throw new Exception("Fail");
        }
    } catch (Exception $_) {
        $a = 'exception';
    }
    // Should not warn
    // Observed: PhanImpossibleCondition Impossible attempt to cast $a of type null to truthy
    if (!$a) {
        echo "Found invalid a\n";
    }
}

function ifelse_701(&$a) {
    if (rand() % 2 > 0) {
        $a = 'x';
    } else {
        $a = null;
    }
    // Should not warn
    // Observed: PhanImpossibleCondition Impossible attempt to cast $a of type null to truthy
    if (!$a) {
        echo "Found invalid a\n";
    }
}

function ifelse_impossible_701(&$a) {
    if (rand() % 2 > 0) {
        $a = 'x';
    } else {
        $a = 'other';
    }
    // Should not warn
    // Observed: PhanImpossibleCondition Impossible attempt to cast $a of type null to truthy
    if (!$a) {
        echo "Found invalid a\n";
    }
}
