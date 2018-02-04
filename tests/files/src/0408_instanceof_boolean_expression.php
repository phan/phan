<?php

class A
{
    public function b(): bool
    {
        return true;
    }
}

/** @param object $object */
function test($object) : bool {
	return $object instanceof A && $object->b(); // <-- Phan should understand this
}

/** @param object $object */
function test2($object) {
	return $object instanceof A && $object->c();  // wrong, should warn
}

/** @param object $object */
function testd($object) {
	$object instanceof A && $object->d();  // wrong, should warn
}

/** @param object $object */
function teste($object) {
	return !($object instanceof A) || $object->b();  // correct
}

/** @param object $object */
function testf($object) : bool {
	return !($object instanceof A) || $object->missingMethod();  // Wrong, should warn
}

/** @param object $object */
function testg($object) {
	!($object instanceof A) || $object->otherMissingMethod();  // Wrong, should warn
}
