<?php
class Stringular
{
    public function __toString()
    {
        return 'test string';
    }
}

function test(string $test, int $arg=0)
{
    echo $test;
}

test(new Stringular());
test(new Stringular(), new Stringular());
