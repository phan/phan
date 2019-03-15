<?php
function test_variable_suggestions() {
    $value3 = 33;
    $unrelatedValuename = 'value';
    if (rand() % 2 > 0) {
        $value1 = 1;
    } else {
        $value2 = 2;
    }
    echo $value1;
    echo $value3;
    echo $unrelatedValueName;
}
test_variable_suggestions();
