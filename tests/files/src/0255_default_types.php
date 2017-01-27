<?php declare(strict_types=1);
namespace NS255 {
    function f1(string $arg) {}
    function f2($a = 1) { f1($a); }
    f2();
}
