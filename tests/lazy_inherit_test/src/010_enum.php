<?php
enum E010: string implements V010Labeled
{
    case A = 'a';
    public function label(): string { return $this->value; }
}

function expect_int_010(int $i): void {}

function test010(E010 $e): void
{
    expect_int_010($e->label());
    expect_int_010($e->weight());
    expect_int_010(E010::from('a')->value);
}
