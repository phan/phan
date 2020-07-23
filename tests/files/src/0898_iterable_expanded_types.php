<?php
namespace NS;

class foo
{
}

class bar extends foo
{
}

/**
 * @return iterable<bar>
 */
function generateSomeBar(): iterable
{
    yield new bar;
}

/**
 * @return iterable<stdClass>
 */
function generateSomeStdClass(): iterable
{
    yield new \stdClass;
}

/**
 * @param iterable<foo> $foos
 */
function loopOverFoo(iterable $foos): void
{
    foreach ($foos as $foo) {
        var_dump($foo);
    }
}

loopOverFoo(generateSomeBar());
loopOverFoo(generateSomeStdClass());
