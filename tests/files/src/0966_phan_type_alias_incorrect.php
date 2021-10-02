<?php

class A {
}

/**
 * @phan-type false = true
 * @phan-type true = false
 * @phan-type void = A
 * @phan-type B = A
 * @phan-type C = B
 * @phan-type B = C
 * @phan-type D =
 * @phan-type E
 * @param C $x
 * @param B $y
 */
function example($x, $y): void {
    '@phan-debug-var $x, $y';
}
example(new A(), new A());
