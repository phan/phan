<?php
/** @return Generator<int> */
function test195() {
    echo "Running\n";
    yield 123;
}
test195();  // should warn because a generator is called but not used
abstract class Base195 {
    public abstract function test(): Generator;
}

function testmethod195(Base195 $base) {
    $base->test();
}
