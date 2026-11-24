<?php
class C { protected(set) string $x = "a"; private(set) int $y = 1; public private(set) int $z = 1; public(set) readonly int $r; final protected(set) int $f = 1;
 public function __construct(private(set) int $p = 1, protected(set) readonly int $q = 2) {} }
