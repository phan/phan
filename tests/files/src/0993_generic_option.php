<?php

declare(strict_types=1);

// This tests type checking for Phan's own Library\Option type, simplified a little bit.

namespace NS993;

use Exception;

/**
 * @template T
 */
abstract class Option
{
    /**
     * @template E
     * @param E $else
     * @return T|E
     */
    abstract public function getOrElse($else);

    abstract public function isDefined(): bool;

    /**
     * @return T
     */
    abstract public function get();
}

/**
 * @template T
 * @extends Option<T>
 */
class Some extends Option
{
    /** @var T the value wrapped by this Some<T>*/
    private $t;

    /**
     * @param T $t
     */
    public function __construct($t)
    {
        $this->t = $t;
    }

    public function isDefined(): bool
    {
        return true;
    }

    /**
     * @return T
     */
    public function get()
    {
        return $this->t;
    }

    /**
     * @param mixed $else @phan-unused-param
     * @return T
     */
    public function getOrElse($else)
    {
        return $this->t;
    }
}

/**
 * @extends Option<never>
 */
final class None extends Option
{
    public function isDefined(): bool
    {
        return false;
    }

    /**
     * @template E
     * @param E $else
     * @return E
     */
    public function getOrElse($else)
    {
        return $else;
    }

    /**
     * @return never
     */
    public function get()
    {
        throw new Exception("Cannot call get on None");
    }
}

/**
 * This implementation of None is incorrect and shouldn't pass type checks.
 * @extends Option<null>
 */
final class NoneNull extends Option
{
    public function isDefined(): bool
    {
        return false;
    }

    /**
     * @template E
     * @param E $else
     * @return E
     */
    public function getOrElse($else)
    {
        return $else;
    }

    /**
     * @return never
     */
    public function get()
    {
        throw new Exception("Cannot call get on None");
    }
}

class Foo {}

/** @return Option<Foo> */
function maybeFoo() {
    return rand() ? new Some(new Foo) : new None;
}

class Test
{
    /** @var Option<Foo> */
    public $optionFoo;
    /** @var Option<null> */
    public $optionNull;
    /** @var Some<Foo> */
    public $someFoo;
    /** @var None */
    public $none;
}

$test = new Test;

// OK:
$test->optionFoo = maybeFoo();
$test->optionFoo = new Some(new Foo);
$test->optionFoo = new None;
// Error:
$test->optionFoo = new Some(null);
$test->optionFoo = new NoneNull;
$test->optionFoo = new Foo;
$test->optionFoo = null;

// OK:
$test->optionNull = new None;
$test->optionNull = new Some(null);
$test->optionNull = new NoneNull;
// Error:
$test->optionNull = maybeFoo();
$test->optionNull = new Some(new Foo);
$test->optionNull = new Foo;
$test->optionNull = null;

// OK:
$test->someFoo = new Some(new Foo);
// Error:
$test->someFoo = maybeFoo(); // Error
$test->someFoo = new None; // Error
$test->someFoo = new Some(null);
$test->someFoo = new NoneNull;
$test->someFoo = new Foo;
$test->someFoo = null;

// OK:
$test->none = new None;
// Error:
$test->none = maybeFoo();
$test->none = new Some(new Foo);
$test->none = new Some(null);
$test->none = new NoneNull;
$test->none = new Foo;
$test->none = null;

// Check function return value / parameter passing too

// OK:
/** @return Option<Foo> */ function test1() { return maybeFoo(); }
/** @return Option<Foo> */ function test2() { return new Some(new Foo); }
/** @return Option<Foo> */ function test3() { return new None; }
// Error:
/** @return Option<Foo> */ function test4() { return new Some(null); }
/** @return Option<Foo> */ function test5() { return new NoneNull; }
/** @return Option<Foo> */ function test6() { return new Foo; }
/** @return Option<Foo> */ function test7() { return null; }

/** @param Option<Foo> $x*/
function test($x) {
    var_dump($x);
}
// OK:
test(maybeFoo());
test(new Some(new Foo));
test(new None);
// Error:
test(new Some(null));
test(new NoneNull);
test(new Foo);
test(null);


// Test union types with unspecified type parameters (#5052)

/** @return Option<mixed> */
function maybeMixed1() {
    return rand() ? new Some(42) : new None;
}

function maybeMixed2(): Option {
    return rand() ? new Some(42) : new None;
}

$option0 = rand() ? new Some(42) : new None;
$option1 = maybeMixed1();
$option2 = maybeMixed2();
'@phan-debug-var $option0, $option1, $option2';

$val0 = $option0->getOrElse(null);
$val1 = $option1->getOrElse(null);
$val2 = $option2->getOrElse(null);
'@phan-debug-var $val0, $val1, $val2';
