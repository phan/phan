<?php
class C017 extends V017Base {}
class C017Override extends V017Base
{
    public function risky(): void
    {
        throw new LogicException('x');
    }
}

function test017(C017 $c, C017Override $o): void
{
    $c->risky();
    $c->riskier();
    $o->riskier();
}
