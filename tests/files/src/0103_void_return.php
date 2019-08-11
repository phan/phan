<?php

/** @return null */
function f0() { return null; }

/** @return null */
function f1() { return 42; }

/** @return null */
function f2() {}

/** @return void - doesn't warn because null can currently cast to void and callers can't tell. */
function g0() { return null; }

/** @return void */
function g1() { return 42; }

/** @return void */
function g2() {}
