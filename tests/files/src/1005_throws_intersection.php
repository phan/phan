<?php

interface ExceptionA {}
interface ExceptionB {}
class MyException extends Exception implements ExceptionA, ExceptionB {}

class NotAnException {}

/**
 * This emits issues about throwing interface types, maybe that shouldn't happen for intersections?
 * @throws ExceptionA&ExceptionB
 */
function test1() {
	throw new MyException();
}

/**
 * The "Throwable" interface is a special case and does not emit issues.
 * @throws MyException&Throwable
 */
function test2() {
	throw new MyException();
}

/**
 * This must still emit issues about using invalid types in "throws" when they're in an intersection type.
 * @throws NotAnException&NotDefinedException
 */
function test3() {
	throw new MyException();
}
