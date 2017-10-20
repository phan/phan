<?php

const VPRINTF_ARGS_1 = [33];
const VPRINTF_ARGS_2 = [0 => 33, 1 => 34];

vprintf("%s", []);
$x = vsprintf("some literal", []);

vprintf("%d dollars 100% down\n", [44]);  // should warn
vprintf("Not using args", [3]);
vprintf("Not using args", VPRINTF_ARGS_1);
vprintf("Not using %d\n" . " args\n", VPRINTF_ARGS_2);

vprintf([], 2);  // invalid

vfprintf(STDERR, "Hello world\n", []);
vfprintf(STDERR, "Hello %s", ["world"]);
vfprintf(STDERR, "Hello %s", "world");
vfprintf(STDERR, "Hello %s", true);
vfprintf(STDERR, "Hello %10s", ["world"]);

$tmp = ['arg %s', ['name']];
vprintf(...$tmp);  // not analyzed, but should not crash.

// TODO test printf("%d", $strVal) and warn
