<?php
class C252_1 {
    const A = 1;
    public const B = 2;
    protected const C = 3;
    private const D = 4;
    public function f1() {
        return [
            C252_1::A, C252_1::B, C252_1::C, C252_1::D
        ];
    }
}
(new C252_1)->f1();
class C252_2 extends C252_1 {
    public function f2() {
        return [
            C252_1::A, C252_1::B, C252_1::C, C252_1::D,
        ];
    }
}
(new C252_2)->f2();
class C252_3 {
    public function f3() {
        return [
            C252_1::A, C252_1::B, C252_1::C, C252_1::D,
        ];
    }
}
(new C252_3)->f3();
function f4() {
    return [
        C252_1::A, C252_1::B, C252_1::C, C252_1::D
    ];
}
f4();
