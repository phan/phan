<?php
class C016Plain extends V016Plain {}
class C016PlainChild extends C016Plain {}
/**
 * @extends V016Generic<int>
 */
class C016Generic extends V016Generic {}
class C016Tmpl extends V016TemplateMethod {}

function expect_int_016(int $i): void {}

function test016(C016Plain $p, C016PlainChild $pc, C016Generic $g, C016Tmpl $t): void
{
    expect_int_016($p->untyped(1));
    expect_int_016($pc->untypedArg());
    expect_int_016($p->noReturn());
    expect_int_016($g->untyped());
    expect_int_016($t->identity('s'));
    expect_int_016($t->plain());
}
