<?php
class MyClass858 {
    public function myMethod() {
    }
}

class MyCountableClass implements Countable {
    public function count() {
        return 0;
    }
}

/**
 * @param MyClass858&Countable $param
 * @return MyClass858&Countable
 */
function test($param) {
    printf("Count is %d\n", $param->count());
    $param->myMethod();
    $param->missingMethod();
    return $param;
}

// Phan does not currently support real intersection types in phpdoc or inferences.
// This tests that the fallback does something reasonable, and doesn't crash.
function test_infer(MyClass858 $x) {
    if ($x instanceof Countable) {
        test($x);
    } elseif ($x instanceof ArrayAccess) {
        test($x);
    }
}

/**
 * @param list<\MyClass858&\Countable> $values
 */
function test_list(array $values) {
    foreach ($values as $value) {
        test($value);
        test_infer($value);
        $value->otherMissingMethod();
    }
}
test_list([new stdClass()]);
test_list([new MyCountableClass()]);
