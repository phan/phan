<?php

trait T350 {
    private $a = 5;
    private function foo(int $x) : int {
        $old = $x;
        $this->a  = $x;
        $this->b  = $x * 2;
        return $old;
    }
}

class C350 {
    use T350 {
        foo as foo2;
    }
    public function bar() {
        $this->foo(55);
        $this->foo2("56");
    }
}
$c350 = new C350();
$c350->bar();
