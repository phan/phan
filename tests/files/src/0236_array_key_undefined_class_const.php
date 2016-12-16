<?php

class FooParent {
    const X = 'x';
}

class Foo extends FooParent {
    public static function my_method() {
        $labels = [
            Bar::A => 'a',
            Bar::B => 'a',
            'well' => Bar::C,
            parent::X => 'c',
            parent::Y => 'd',
            self::X => static::X,  // good
            'key' => static::UNDECLARED_PAST_FIRST_5,
        ];
        return $labels;
    }
}
class Bar {
    const A = 'a';
}
