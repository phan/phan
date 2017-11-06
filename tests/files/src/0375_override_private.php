<?php

class a375{
    private function method() : int {
        return 33;
    }

    private function method2() : int {
        return 33;
    }

    public function foo() {
        return $this->method();
    }

    public function foo2() {
        return $this->method2();
    }
}

class b375 extends a375 {
    private function method($a) : string {
        return "xyz $a";
    }

    // Even though this override is public, b375->foo2() will still call a375->method2(), not the overriding method.
    // Phan shouldn't warn.
    public function method2($a) : string {
        return "xyz $a";
    }

    public function bar($a) {
        return $this->method($a);
    }
}
printf("%d\n%s\n", (new b375())->foo(), (new b375())->foo2(), (new b375())->bar("abc"));
