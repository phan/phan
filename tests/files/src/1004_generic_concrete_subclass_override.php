<?php

/** @template T */
abstract class SomeClass {
	/** @return T */
	abstract function doWork();
}

/** @inherits SomeClass<?int> */
class SomeClass0 extends SomeClass {
	function doWork() {
		return 0;
	}
}

/** @inherits SomeClass<?int> */
class SomeClass1 extends SomeClass {
	function doWork(): ?int {
		return 0;
	}
}

/** @inherits SomeClass<?int> */
class SomeClass2 extends SomeClass {
	/** @return ?int */
	function doWork() {
		return 0;
	}
}

/** @inherits SomeClass<?int> */
class SomeClass3 extends SomeClass {
	/** @return ?int */
	function doWork(): ?int {
		return 0;
	}
}
