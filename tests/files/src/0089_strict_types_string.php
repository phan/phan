<?php declare(strict_types=1);
class C {
    function __toString() { return 'str'; }
}
$v = strlen(new C);
