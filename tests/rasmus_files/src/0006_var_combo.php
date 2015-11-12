<?php
class A {
    function func(int $arg):array { return 1; }
}
$a = A::func();
A::func($a);
