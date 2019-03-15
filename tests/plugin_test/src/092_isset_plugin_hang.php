<?php
declare(strict_types = 1);

class TestPhan
{
    const TOTO = [];
    public function test()
    {
        /* Comment next line to unlock Phan */
        return isset(self::TOTO['a']);
    }
}
