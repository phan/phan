<?php

// @phan-file-suppress PhanThrowTypeAbsent, PhanUnreferencedFunction
namespace SomeNS;

use AssertionError;
use InvalidArgumentException;
use RuntimeException;
use Traversable;

/**
 * @param int|string $x
 * @phan-assert string $x
 */
function my_assert_string($x) {
    if (!is_string($x)) {
        throw new InvalidArgumentException("expected a string, got $x");
    }
}

/**
 * @template TClassName
 * @param class-string<TClassName> $className
 * @param mixed $mixed the value we're making the assertion on
 * @phan-assert TClassName $mixed
 * @return void
 * @throws InvalidArgumentException
 */
function my_assert_instance(string $className, $mixed) {
    if (!$mixed instanceof $className) {
        throw new InvalidArgumentException("expected to find instance of $className but failed");
    }
}

/**
 * @param mixed $x
 * @param mixed $y
 */
function my_expect_mixed($x, $y) {
    my_assert_string($x);
    echo intdiv($x, 2);  // should infer 'string' and warn
    my_assert_instance(\stdClass::class, $y);
    echo intdiv($y, 2);  // should infer '\stdClass' and warn
}

/**
 * @param mixed $arg
 * @phan-assert !null $arg
 */
function my_assert_not_null($arg) {
    if ($arg === null) {
        throw new AssertionError("Reject null");
    }
}

/**
 * @param mixed $arg
 * @phan-assert !false $arg
 */
function my_assert_not_false($arg) {
    if ($arg === false) {
        throw new AssertionError("Reject false");
    }
}

/**
 * @param mixed $arg
 * @phan-assert !Traversable $arg
 */
function my_assert_not_traversable($arg) {
    if ($arg instanceof Traversable) {
        throw new AssertionError("Expected a non Traversable");
    }
}


/**
 * @param null|false|string $arg
 * @param ?\stdClass $obj
 * @param \ArrayObject|\ast\Node $node
 */
function test_misc_negated_assertions($arg, $obj, $node)
{
    my_assert_not_null($arg);
    my_assert_not_null($obj);
    echo intdiv($arg, 2);  // should infer 'false|string' and warn
    echo intdiv($obj, 2);  // should infer '\stdClass' and warn

    my_assert_not_false($arg);
    echo intdiv($arg, 2);  // should infer 'string' and warn

    my_assert_not_traversable($node);
    echo strlen($node);  // should infer '\ast\Node' and warn
}

class TestBase {
    /**
     * @phan-assert-true-condition $cond
     */
    public static function assertTrue(bool $cond, string $message) {
        if (!$cond) {
            throw new RuntimeException("This failed: $message");
        }
    }

    /**
     * @phan-assert-false-condition $cond
     */
    public static function assertFalse($cond, string $message) {
        if ($cond) {
            throw new RuntimeException("This failed: $message");
        }
    }
}

function test_assert_true_false($a, $b, $c) {
    TestBase::assertTrue($a instanceof \stdClass, 'message');
    TestBase::assertTrue($b instanceof missingClass, 'message');  // should not crash
    TestBase::assertFalse($c !== 5, 'message');
    echo strlen($a);  // should infer stdClass and warn
    echo $b;  // should infer missingClass and warn
    echo strlen($c);
}
