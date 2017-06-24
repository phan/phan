<?php
/** @param resource $var */
function emitType($var) {}

function emitResourceType(string $var) {}

function check($var) {
	if (is_array($var)) {
		emitType($var);
	} elseif (is_bool($var)) {
		emitType($var);
	} elseif (is_callable($var)) {
		emitType($var);
	} elseif (is_double($var)) {
		emitType($var);
	} elseif (is_float($var)) {
		emitType($var);
	} elseif (is_int($var)) {
		emitType($var);
	} elseif (is_integer($var)) {
		emitType($var);
	} elseif (is_bool($var)) {
		emitType($var);
	} elseif (is_long($var)) {
		emitType($var);
	} elseif (is_null($var)) {
		emitType($var);
	} elseif (is_numeric($var)) {
		emitType($var);
	} elseif (is_object($var)) {
		emitType($var);
	} elseif (is_real($var)) {
		emitType($var);
	} elseif (is_resource($var)) {
		emitResourceType($var);
	} elseif (is_scalar($var)) {
		emitType($var);
	} elseif (is_string($var)) {
		emitType($var);
	} elseif (is_iterable($var)) {
		emitType($var);
		foreach ($var as $v) { }  // sanity check that iteration works
	}
}

check(null);
