<?php

/**
 * @template T0
 * @template T1
 */
class Generic {
}

class Err1 extends Generic {}

/**
 * @extends Generic
 */
class Err2 extends Generic {}

/**
 * @extends Generic<int>
 */
class Err3 extends Generic {}

/**
 * @extends Generic<int,int>
 */
class OK1 extends Generic {}

/**
 * @template X0
 * @template X1
 * @extends Generic<X0,X1>
 */
class OK2 extends Generic {}
