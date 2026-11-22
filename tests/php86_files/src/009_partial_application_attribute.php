<?php
#[Attribute]
final class Attr009 {
    public function __construct(public int $a = 0) {}
}
// Partial function application in an attribute argument used to fail an assertion in ArgumentType.
#[Attr009(?)]
function f009(): void {}
