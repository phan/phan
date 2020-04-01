<?php

class C5 {
    protected const Y = 'prefix';
    public const X = self::Y . 'suffix';
}

class D5{
    const Z = C5::X;
}
echo D5::Z;
