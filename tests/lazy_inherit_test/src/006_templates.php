<?php
/**
 * @extends V006Box<string>
 */
class S006StringBox extends V006Box {}
// A generic parent without (at)extends
class S006Plain extends V006Box {}

function expect_int_006(int $i): void {}

function test006(S006StringBox $s, S006Plain $p): void
{
    expect_int_006($s->get());
    expect_int_006($s->count());
    expect_int_006($p->count('x'));
    $box = new S006StringBox(42);
    expect_int_006($box->get());
}
