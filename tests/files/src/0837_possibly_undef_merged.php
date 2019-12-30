<?php declare(strict_types=1);

namespace NS837;

/**
 * Factory function that returns a new class instance
 * @return object
 */
function create() {
	return new class {
		public function func() {
		}
	};
};

// Some function that only throws an exception
function abortFunc() {
	throw new \Exception;
};

if (rand() % 2) {
	$obj = create();
} else {
    if(rand() % 2) {  // behavior still changes with `else if` vs `elseif`
        $obj = create();
    } else {
        abortFunc();
    }
}
'@phan-debug-var $obj';

// PhanNonClassMethodCall Call to method func on non-class type ?object
$obj->func();
