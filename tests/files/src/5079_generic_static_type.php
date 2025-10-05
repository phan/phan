<?php

/** @template T */
class Box {
	/** @param T $t */
	function __construct(
		public $t
	) {}

	function getBox(): static {
		return $this;
	}
}

/** @return Box<int> */
function newBoxInt() {
	return new Box(1);
}

/** @param Box<int> $box */
function acceptBoxInt($box) { return $box; }
/** @param Box<string> $box */
function acceptBoxString($box) { return $box; }

$a = newBoxInt();
var_dump(acceptBoxInt($a));
$b = newBoxInt();
var_dump(acceptBoxString($b));
$c = newBoxInt()->getBox();
var_dump(acceptBoxInt($c));
$d = newBoxInt()->getBox();
var_dump(acceptBoxString($d));
'@phan-debug-var $a, $b, $c, $d';
