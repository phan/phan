<?php

class C1 {
    public $p = 42;
}

trait T1 {
    public $p = 'string';
}

class C2 extends C1 {
    use T1;
}
