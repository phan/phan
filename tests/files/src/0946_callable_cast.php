<?php

class A946 {
    /**
     * @param array $params
     * @return null
     */
    public function __invoke($params) {
        var_dump($params);
        return null;
    }
}

/**
 * @param ?callable(array):(?bool) $c
 * @return ?bool
 */
function accepts_callable($c) {
    if (!$c) {
        return false;
    }
    return $c([]);
}

/**
 * @param ?callable():(?bool) $c
 * @return ?bool
 */
function accepts_incompatible_callable($c) {
    if (!$c) {
        return false;
    }
    return $c();
}

function accepts_any_callable(callable $c) {
    $c([2]);
}

accepts_callable(new A946());
accepts_incompatible_callable(new A946());
accepts_any_callable(new A946());
accepts_any_callable(new ArrayObject());
