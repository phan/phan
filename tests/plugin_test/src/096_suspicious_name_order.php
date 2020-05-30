<?php

function test_order($first, $second, $third) {
    var_export([$first, $second, $third]);
}
/**
 * @param object $o
 * @param mixed $first
 * @param mixed $second
 * @param mixed $third
 */
function test_caller($o, $first, $second, $third) {
    test_order($second, $first, $third);
    test_order($second, $third, $first);
    test_order($o->second, $o->first, $o->third);
    test_order($o->getThird(), $o->getSecond(), $o->getFirst());
}

function some_check(stdClass $first, bool $second) {
    var_export([$first, $second]);
}
function some_check_chain(stdClass $first, bool $second, int $third) {
    var_export([$first, $second, $third]);
}

function test_no_false_positive() {
    $objFirst = new stdClass();
    $secondValue = false;
    $third = 1;

    some_check($secondValue, $objFirst);
    some_check_chain($secondValue, $third, $objFirst);

    $objFirst = false;
    $secondValue = new stdClass();
    $third = 1;
    // Don't emit this suggestion if the fix wouldn't work
    some_check($secondValue, $objFirst);
    some_check_chain($secondValue, $third, $objFirst);  // Should warn about passing second argument $third with the exact same name as the third parameter of some_check_chain
}

function my_strpos(string $message, string $needle, string $haystack) {
    fwrite(STDERR, "$message: Looking for $needle\n");
    return strpos($needle, $haystack);  // this is wrong, the haystack should go first
}
var_export(my_strpos('debug msg', '.', 'foo.bar'));

function has_optional($first = false, $second = true, $third = false) {
    var_export([$first, $second, $third]);
}

class Test96 {
    /** @var bool */
    public static $third = true;
    /** @var bool */
    public $SECOND = false;

    public function invoke_has_optional($first, $second, $third) {
        has_optional($first);
        has_optional($second);
        has_optional($third);
        has_optional($first, $third);
        has_optional($third, $first);
        has_optional(self::$third, false, $this->SECOND);
    }
}
(new Test96)->invoke_has_optional(false, true, true);
