<?php
// The quick_mode=false variant of src/016_untyped_roots.php (see .phan/config_slow.php)
class C020Plain extends V016Plain {}
class C020PlainChild extends C020Plain {}
class C020Tmpl extends V016TemplateMethod {}

function expect_int_020(int $i): void {}

function test020(C020Plain $p, C020PlainChild $pc, C020Tmpl $t): void
{
    expect_int_020($p->untyped(1));
    expect_int_020($pc->untypedArg('x'));
    expect_int_020($pc->untyped());
    expect_int_020($p->noReturn());
    expect_int_020($t->identity('s'));
    expect_int_020($t->plain());
}
