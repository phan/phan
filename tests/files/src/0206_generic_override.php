<?php

/**
 * @template-covariant T (NOTE: Phan is currently treating (at)template-covariant as an alias of (at)template)
 */
class CA {
    /** @var T */
    protected $p;

    /** @param T $p */
    public function __construct($p) {
        $this->p = $p;
    }

    /**
     * @param T $p
     * @return T
     */
    public function f($p) {
        return $this->p;
    }
}

/**
 * @inherits CA<int>
 */
class CB extends CA {
    /**
     * @param int $p
     * @return int
     */
    public function f($p) {
        return $this->p;
    }
}
