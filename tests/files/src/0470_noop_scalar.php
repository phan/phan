<?php
// Tests of warning about noops, and warnings about bad variables/expressions encapsulated in strings
function aScalarNoop(array $x) {
    "x is $x";
    'x == $x';
    2;
    .5;

    <<<EOT

    x is $x
EOT;
}

function bScalarNoop(stdClass $x) {
    <<<'EOT'

    x is $x

EOT;
    "x :: $x";
    .75;
}
1.0;
