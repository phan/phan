<?php
$f = fn(array $x) => $x;
fn(): int => $x;
fn($x = 42) => yield $x;
fn(&$x) => $x;
fn&($x) => $x;
fn($x, ...$rest) => $rest;
static fn() => 1;
$f = static fn() => 2;
