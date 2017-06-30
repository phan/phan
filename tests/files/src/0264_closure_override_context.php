<?php
namespace closure264;

class BoundClass264 {
    /** @var string $b */
    protected $b = 'a';

    /** @var string $b */
    public $c = 'a';

    protected static function a_static_method() { }
}

class TestFramework {
    /** @var string */
    protected $_frameworkProperty = 'value';
    public function mockA() : string {  // mockA() is buggy, it didn't return anything.
        // BoundClass264::a_static_method(); // What? This should emit an issue.

        /**
         * blank, should be ignored?
         * @PhanClosureScope
         */
        $w = function() : string {
            // BoundClass264::a_static_method();  // should emit an issue?
            return $this->_frameworkProperty;
        };

        /**
         * @PhanClosureScope BoundClass264
         */
        $x = function() : string {
            BoundClass264::a_static_method();
            self::a_static_method();
            return $this->b . $this->c;
        };

        /**
         * @PhanClosureScope \closure264\BoundClass264
         */
        $y = function() : string {
            BoundClass264::a_static_method();
            self::a_static_method();
            return $this->b . $this->d;
        };
        /**
         * BoundClass264 scope of a native type(string, array, object, bool, etc.) makes no sense. Phan should warn.
         * @PhanClosureScope string
         */
        $z = function() : string {
            return $this->b . $this->d;
        };
    }
}
