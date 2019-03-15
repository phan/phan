<?php

/**
 * PhanInfiniteRecursion should be emitted - this is calling my_recursive_function without ever returning or creating the class.
 *
 * @return object
 */
function my_recursive_function_bad(int $x) {
    return (new class(my_recursive_function_bad($x - 1)) {
        public $value;

        public function __construct($value) {
            var_export($value);
            $this->value = $value;
        }
    });
}

/**
 * PhanInfiniteRecursion should be emitted - this is calling my_recursive_function without ever returning or creating the class.
 *
 * @return object
 */
function my_recursive_function_good(int $x) {
    if ($x <= 0) {
        return new stdClass();
    }
    return (new class(my_recursive_function_good($x - 1)) {
        public $value;

        /**
         * @param object $value
         */
        public function __construct($value) {
            var_export($value);
            $this->value = $value;
        }
    });
}
