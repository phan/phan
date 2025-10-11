<?php

// Should NOT warn - constructors are exempt from private final warning in PHP 8.0+
class Foo {
	final private function __construct() {}
}

// Should NOT warn - abstract class with private final constructor
abstract class Action {
	/** Don't allow direct instantiations of this class, use newFromContext instead. */
	final private function __construct() {}
}

// Should warn - regular private final method (not a constructor)
class Bar {
	final private function notConstructor() {}
}

// Should NOT warn - constructors in various contexts
class Baz {
	final private function __construct() {}

	public static function create(): self {
		return new self();
	}
}

// Should warn - private final constant is still not allowed
class WithConstant {
	private final const X = 123;
}
