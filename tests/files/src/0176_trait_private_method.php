<?php

trait T176 {
    protected function f1() {}
    private function f2() {}
}

class C176 {
    use T176;
    function g1() {
        return $this->f1();
    }
    function g2() {
        echo "Calling f2\n";
        return $this->f2();
    }
}

class D176 extends C176{
    function methodOfSubclass1() {
        echo "Calling f1 from subclass\n";
        return $this->f1();
    }
    function methodOfSubclass2() {
        echo "Calling f2 from subclass\n";
        return $this->f2();
    }
}
(new D176())->g1();  // for demonstrative purposes. This is fine.
(new D176())->g2();  // for demonstrative purposes. This is fine.
(new D176())->methodOfSubclass1();  // for demonstrative purposes. This is fine.
(new D176())->methodOfSubclass2();  // for demonstrative purposes. This will throw an Error
