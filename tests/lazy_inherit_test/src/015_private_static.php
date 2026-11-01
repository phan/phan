<?php
class C015 extends V015Base
{
    public function test(): void
    {
        $this->secret();
        expect_int_015(self::helper('x'));
        expect_int_015(static::create());
    }
}

function expect_int_015(int $i): void {}

function test015(): void
{
    C015::helper('x');
    expect_int_015(C015::create());
    expect_int_015(C015::create()->usesSecret());
}
