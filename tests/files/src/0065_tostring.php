<?php
class Stringular
{
    public function __toString()
    {
        return 'test string';
    }
}

function test(string $test)
{
    echo $test;
}

test(new Stringular());
