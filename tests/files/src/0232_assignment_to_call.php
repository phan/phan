<?php
class C { function f() { return [ 42 ]; } }
function f() { return [ 42 ]; }
function g(bool $p) {
    print "$p\n";
}
g(f()[0] = 'call');
g((new C)->f()[0] = 'method');
