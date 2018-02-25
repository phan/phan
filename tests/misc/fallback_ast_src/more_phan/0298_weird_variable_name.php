<?php
error_reporting(E_ALL);

function testIrregularVar128() {
    ${42} = ['value'];
    try {
    echo intdiv(${42}, 3);  // intdiv takes int but this is string[]
    } catch(Throwable $e) {}
    echo "Next\n";
    ${true} = ['value'];
    ${rand() % 2 == 0} = ['value'];
    ${[2]} = ['value'];  // Seriously, this is an alias for $Array?
    echo intdiv(${[3]}, 4);
    echo "Done\n";
}
testIrregularVar128();
