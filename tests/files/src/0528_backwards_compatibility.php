<?php
class PHP5Class {
	public $arr = [
		'key' => 'value',
	];
};
$class = new PHP5Class();

$var = 'arr';
echo $class->$var['key'], PHP_EOL;  // This behaves differently in PHP 5, Phan should warn
echo $class->{$var}['key'], PHP_EOL;  // This behaves the same way in PHP 5 and 7, Phan should not warn

/**
 * Phan should detect PHP5 incompatible expressions within a class function argument
 */
class PHP5Class2 {
	public function func($var) {
		return $var;
	}
}
$class2 = new PHP5Class2();
echo $class2->func($class->$var['key']), PHP_EOL;
echo $class2->func($class->{$var}['key']), PHP_EOL;

/**
 * Phan should detect PHP5 incompatible expressions within an array key
 */
$arr = [
	'value' => 'value2',
];
echo $arr[$class->$var['key']], PHP_EOL;
echo $arr[$class->{$var}['key']], PHP_EOL;
