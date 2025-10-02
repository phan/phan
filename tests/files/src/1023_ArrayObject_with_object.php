<?php

class Demo {
	public $value;
}

$d = new Demo();

$unknown = (random_int(0, 1) === 1 ? $d : [$d]);

$cases = [
	new ArrayObject($d),
	new ArrayIterator($d),
	new ArrayObject([$d]),
	new ArrayIterator([$d]),
	new ArrayObject($unknown),
	new ArrayIterator($unknown),
];
