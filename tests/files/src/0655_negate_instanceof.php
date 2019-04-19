<?php

namespace NS655;

class TestClass {
}
/**
 * @param ?TestClass|?array<int,TestClass> $value
 */
function elseifInfer($value) {
    if ($value instanceof TestClass) {
        $value = [$value];
    } else {
        echo strlen($value);
    }
    foreach ($value ?? [] as $v) {
        echo strlen($v);
    }
}
