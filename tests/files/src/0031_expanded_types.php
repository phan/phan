<?php
interface A {}
class B implements A {}

function f(A $a) {
}

f(new B);
