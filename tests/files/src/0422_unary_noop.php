<?php
function example(int $x) {
    +$x;
    -$x;
    !$x;
    ~$x;
    @$x;  // In the general case, `@` would suppress undeclared variable errors.
}
