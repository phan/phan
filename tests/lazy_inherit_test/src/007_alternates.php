<?php
if (rand(0, 1)) {
    class C007 extends V007Base {}
} else {
    class C007 extends V007Base
    {
        public function go(): int { return 2; }
    }
}
class C007Alt extends V007Alt {}

function expect_string_007(string $s): void {}

function test007(C007 $c, C007Alt $a): void
{
    expect_string_007($c->go());
    $a->alt(1);
}
