<?php declare(strict_types=1);
interface I {}
class C213A implements I {}
class C213B implements I {}
function f(C213A $a) {}
f(new C213B);
