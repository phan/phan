<?php

function test_impossible_type_comparison695(stdClass $obj, ?stdClass $nullableObj, bool $bool, ?bool $nullableBool) {
    // All of these checks on $obj are nullable
    var_export($obj === null);
    var_export($obj !== null);
    var_export($obj === true);
    var_export($obj === $bool);
    var_export($obj !== $bool);
    var_export($obj !== $nullableBool);

    var_export($bool === null);  // impossible

    var_export($nullableObj === null);
    var_export($nullableObj !== null);
    var_export($nullableObj === true);
    var_export($nullableObj === $obj);
    var_export($nullableObj === $nullableBool);
}
