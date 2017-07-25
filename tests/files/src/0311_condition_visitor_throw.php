<?php
// Verify that phan can handle weird assertions in ConditionVisitor without throwing.
// TODO: Emit issues, update test to check for issues.
/**
 * @param int $str
 */
function testcondition311($y, $str) {
    if (!0) { }
    if (!STDERR) { }
    if (!!0) { }
    if (!1) { }
    if (!null) { }
    if (!!1) { }
    if (!'') { }
    if (!'aa') { }
    if (!0.0) { }
    if ($y && !$x) { }  // should emit undefined
    if ($y && $x2) { }  // should emit undefined
    if (!$z) { }  // should emit undefined
    if (!is_null($a)) { }  // should emit undefined
    if ($y && is_string($b)) { }  // should emit undefined
    if (is_string($str) && strlen($str) > 0) { }  // should not warn
    if (is_string($str) && strlen($strTypo) > 0) { }  // should not warn
    if (is_string($str) && intdiv($str, 10)) { }  // should warn
    if (($inCondVar = 3) && strlen($inCondVar) > 0) { }  // should warn
    if ($c ?? false) { }  // should emit undefined
    if (~$unaryMissing1) { }  // should emit undefined
    if (-$unaryMissing2) { }  // should emit undefined

    while (~$unaryMissing3) { }  // should emit undefined
    while ($c2 ?? false) { }  // should emit undefined
    while (is_string($str) && strlen($str) > 0) { }  // should not warn
    while (is_string($str) && intdiv($str, 10) > 0) { }  // should warn

    for ( ; ~$unaryMissing3; ) { }  // should emit undefined
    for ( ; $c2 ?? false; ) { }  // should emit undefined
    for ( ; is_string($str) && strlen($str) > 0; ) { }  // should not warn
    for ( ; is_string($str) && intdiv($str, 10) > 0; ) { }  // should warn
    for ( ;$y && $x3; ) { }  // should emit undefined

    $last_error = error_get_last();
    if (is_array($last_error) && $last_error['type'] === E_ERROR) {
        // do something
    }
}
