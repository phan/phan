<?php
/**
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnClass
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnProtectedProperty
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 * @phan-file-suppress PhanUnusedPublicMethodParameter
 */

/**
 * @template T
 */
class CA {
    /**
     * @var T
     * @phan-read-only
     */
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
