<?php
namespace NS46;

trait Tr {
    public static function double(int $a): int {
        return $a * 2;
    }
}
/**
 * @no-named-arguments
 */
class X {
    use Tr;
    public static function foo(int ...$args): void {
        '@phan-debug-var $args';
        var_dump($args);
    }
}
X::foo(a: 123);
X::foo(...['a' => 1]);
X::double(...[1]);
X::double(a: 123);
