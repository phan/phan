<?php

trait T28 {
    public stdClass $prop;
    public Traversable $t;
    public ?ArrayObject $a;
    public static B28 $base;
}

class A28 {
    public A28 $prop;
    public iterable $t;
    public ArrayObject $a;
    public static A28 $base;
}

class B28 extends A28 {
    use T28;
}
