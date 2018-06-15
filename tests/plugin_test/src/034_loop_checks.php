<?php

class Example34 {
	const MAX = 3;
	public $array = [3,4,5,6];

    // TODO: Fix https://github.com/phan/phan/issues/1729
	function processArray() {
		// Ensure that array isn't larger than the limit.
		$count = count($this->array);  // False positive unused variable
		while ($count-- > static::MAX) {
			array_pop($this->array);
		}
	}
}
(new Example34())->processArray();
