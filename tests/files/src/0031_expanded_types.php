<?php
interface A31 {}
class B31 implements A31 {}

function f(A31 $a) {
}

f(new B31);
