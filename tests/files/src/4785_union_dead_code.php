<?php

/** Represents the first union type option. */
class FirstUnionType
{
    /** Invoke the demo method. */
    public function foobar(): void
    {
    }
}

/** Represents the second union type option. */
class SecondUnionType
{
    /** Invoke the demo method. */
    public function foobar(): void
    {
    }
}

/**
 * Calls foobar on any accepted union type.
 *
 * @param FirstUnionType|SecondUnionType $type
 */
function callFoobar(FirstUnionType|SecondUnionType $type): void
{
    $type->foobar();
}

/** @var FirstUnionType|SecondUnionType $var */
$var = new FirstUnionType();
callFoobar($var);
