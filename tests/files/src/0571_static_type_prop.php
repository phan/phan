<?php

/**
 * @property-read static|false $parent3 should not warn, either
 */
abstract class Chain
{
    /** @var static|null */
    public $parent;

    /** @var ?static|?bool */
    public $parent2;

    /** @var static should warn */
    public static $static_prop_parent;

    public function __get(string $name) {
        return new static();
    }
}

class Request extends Chain
{
}

class Fence extends Chain
{
}

function static_prop_check(Request $r, Fence $f, Chain $c) {
    echo strlen($c->parent3);
    echo strlen($r->parent);
    echo strlen($r->parent2);
    echo strlen($r->parent3);
    echo strlen($c->parent);
    echo strlen($c->parent2);
    echo strlen($f->parent);
    echo strlen($f->parent2);
    echo strlen($f->parent3);
    echo Request::$static_prop_parent;
    echo Chain::$static_prop_parent;
}
