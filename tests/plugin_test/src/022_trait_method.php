<?php

trait T22 {
    public function myFunction() { echo "A\n"; }
    public function myUnusedFunction() { echo "B\n"; }
}

interface I22 {
    public function myFunction();
    public function myUnusedFunction();
}

class X22 implements I22 {
    use T22;
}
(new X22())->myFunction();
