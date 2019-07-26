<?php
// Phan can infer that sprintf on literals is itself a literal.
function expects_int(int $a) {
    var_export($a);
}
expects_int(sprintf("Foo %s %d", "arg", "2nd bad arg"));
expects_int(sprintf("Foo %s"));
expects_int(sprintf("Foo %s", "arg", "extra arg"));
expects_int(sprintf("Foo %d", 2+3));
