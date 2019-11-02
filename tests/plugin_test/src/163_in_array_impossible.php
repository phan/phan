<?php
// @phan-file-suppress PhanPluginComparisonNotStrictInCall
namespace NS163;

use stdClass;

$contents = file(__FILE__);
$dump = function ($result) use ($contents) : void {
    $lineno = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0]['line'];
    $line = trim($contents[$lineno - 1]);
    $expr = preg_replace('/.*\$dump\(|\);.*/', '', $line);
    printf("%s == %s\n", $expr, var_export($result, true));
};
$dump(in_array(null, [0]));
$dump(in_array(null, [0], true));
$dump(in_array(0.0, [0], true));
$o = new stdClass();
$o2 = $o;
$test = function (stdClass $o1, stdClass $o2, int $i, int ...$other) use ($dump) {
    $dump(in_array($o1, [$o1], true));
    $dump(in_array($o2, [$o2]));
    $dump(in_array($o2, $other, true));
    $dump(in_array(2.3, $other, true));
    $dump(in_array($o2, $other, false));
    $dump(in_array(2.3, $other, false));
    $dump(in_array($o2, []));
    $dump(in_array($o2, [$i]));
    $dump(in_array($o1, [null]));
    $dump(in_array(false, [$i, $o1, $o2]));
    $dump(array_search(false, [$i, $o1, $o2]));
    $dump(array_search(false, [$i, $o1, $o2], true));
    $dump(array_search(1, [$i, $o1, $o2], true));
    $dump(array_search($o1, ['a' => $i, 'b' => $o2], true));
    $dump(array_search($o2, [], true));
};
$test(new stdClass(), new stdClass(), 2);

