<?php  // Regression test for uncaught IssueException

class Foo13 {
    public static function my_method() {
        return [
            Bar13::A => 'a',
            Bar13::B => 'a',
            'x' => '1',
            'x2' => '1',
            'x3' => '1',
            'x4' => '1',
            'x5' => '1',
            'x6' => '1',
            'x7' => '1',
            'x8' => '1',
            'x9' => '1',
            Bar13::a => 'a',
        ];
    }
}
class Bar13 {
    const A = 'a';
}

var_export(Foo13::my_method());
