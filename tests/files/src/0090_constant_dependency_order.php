<?php
namespace A {
    class C1 {
        const PREMATURE = \B\C2::C;
    }
}
namespace B {
    class C2 {
        const C = 'Hello, World!';
    }
    print \A\C1::PREMATURE . "\n";
}
