<?php

/**
 * @param array $x @phan-output-reference
 */
function fn16(string $a, string $b, &$x) : void {
    $x = [$a, $b];
}

/**
 * @param array &$x
 */
function fn16b(string $a, string $b, &$x) : void {
    $x = [$a, $b];
}

class MyClass {
    /** @var string */
    public $x = 'someValue';
    /** @var string */
    public static $phpdocString = 'someValue';

    public function modifyX() {
        $this->x = 'default';
        fn16b('first', 'second', $this->x);
    }
}

$m = new MyClass();

fn16('/ab/', $argv[0], $m->x);
fn16('/ab/', $argv[0], MyClass::$phpdocString);
$m->x = 'default';  // ignored
fn16b('/ab/', $argv[0], $m->x);
fn16b('/ab/', $argv[0], MyClass::$phpdocString);
