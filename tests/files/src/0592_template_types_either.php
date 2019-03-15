<?php

namespace MyNS;

use ArrayObject;
use RuntimeException;
use stdClass;

/**
 * @template L
 * @template R
 */
abstract class Either {
    public abstract function getLeft();
    public abstract function getRight();
    public abstract function isLeft() : bool;
}

/**
 * @template T
 * @extends Either<T,mixed>
 */
class Left extends Either {
    /** @var T */
    private $value;

    /** @param T $value */
    public function __construct($value) {
        $this->value = $value;
    }

    /** @return T */
    public function getLeft() {
        return $this->value;
    }

    /** @throws RuntimeException */
    public function getRight() {
        throw new RuntimeException("Does not exist");
    }

    public function isLeft() : bool {
        return true;
    }
}

/**
 * @template T
 * @phan-extends Either<mixed,T>
 */
class Right extends Either {
    /** @var T */
    private $value;

    /** @param T $value */
    public function __construct($value) {
        $this->value = $value;
    }

    /** @throws RuntimeException */
    public function getLeft() {
        throw new RuntimeException("Does not exist");
    }

    /** @return T */
    public function getRight() {
        return $this->value;
    }

    public function isLeft() : bool {
        return false;
    }
}

/**
 * @param Either<stdClass,stdClass> $e
 */
function expects_either_object(Either $e) {
    // should warn
    expects_left_object($e);
    if ($e instanceof Left) {
        // should not warn
        expects_left_object($e);
    }
}

/**
 * @param Left<stdClass> $e
 */
function expects_left_object(Left $e) {
    var_export($e);
    echo $e->getLeft();
}

call_user_func(function () {
    $la = new Left(new ArrayObject());
    $ls = new Left(new stdClass());
    $ra = new Right(new ArrayObject());
    $rs = new Right(new stdClass());
    expects_left_object($la);  // should warn
    expects_left_object($ls);  // should not warn
    expects_left_object($ra);  // should warn
    expects_left_object($rs);  // should warn

    expects_either_object($la);  // should warn
    expects_either_object($ls);  // should not warn
    expects_either_object($ra);  // should warn
    expects_either_object($rs);  // should not warn
});

/**
 * @return Either<ArrayObject,ArrayObject>
 */
function returns_either_object(array $args) {
    switch (rand() % 4) {
    case 0:
        return new Left(new ArrayObject());
    case 1:
        return new Left(new stdClass());  // should warn
    case 2:
        return new Right(new stdClass());  // should warn
    case 3:
        return new Right($args);  // should warn
    default:
        return new Right(new ArrayObject());  // should not warn
    }
}
