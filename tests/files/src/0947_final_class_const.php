<?php
class A947 {
    // Should warn about compatibility with php 8.1
    final protected const X = 123;
    // Should parse but emit PhanInvalidNode, but php 8.3 made this an immediate syntax error
    abstract const Y = 123;
    static const Z = 123;
}
class B947 extends A947 {
    protected const X = 45;
}
var_export(A947::X);
var_export(A947::Y);
