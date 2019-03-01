<?php declare(strict_types=1);

namespace TestGeneric;

use stdClass;

/**
 * @template T
 * @method int findOneBy(array<string,mixed> $criteria, array<string,string> $orderBy = null)
 * @method int findGenericBy(array<int,T> $args)
 */
class X {
    /** @param T $arg */
    public function __construct($arg) {
        var_export($arg);
    }
    public function __call(string $method, $args) {
        return count($args) + strlen($method);
    }
}
$x = new X(new stdClass());
$x->findOneBy([], []);
$x->findOneBy([2], [2]);
$x->findOneBy(['key' => new stdClass()], ['key' => 'value']);
$x->findGenericBy(2);
$x->findGenericBy(new stdClass());
