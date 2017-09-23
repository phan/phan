<?php

class A2
{
    public function alwaysReturns() : int
    {
        $flags = rand() - 10;
        if ($flags) {
            return 4;
        } else if ($flags > 0) {
            return 3;
        }
        return 5;
    }
}
