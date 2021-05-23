<?php

interface I936 {
    public function returnsString(): string;
}
function test_intersection_936(ArrayAccess $t) {
    if ($t instanceof I936) {
        '@phan-debug-var $t';
        echo strlen($t->count()); // not defined, that requires Countable.
        $t->offsetSet('key', 'value');
        echo intdiv($t->returnsString(), 2);
    }
    echo "After merging union types\n";
    '@phan-debug-var $t';
}

trait T936 {
    public function myMethod() {
        if ($this instanceof C936) {
            '@phan-debug-var $this';
            $this->otherTraitMethod(123);
            var_dump($this->count());
        }
    }
    public function otherTraitMethod(int $arg) {
        echo $arg, "\n";
    }
}

class C936 extends ArrayObject {
    use T936;
}
