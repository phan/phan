<?php
class A {
    // TODO bad expression
    public const a = b = "hello";
}

echo A::b;