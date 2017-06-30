<?php

function testInstanceofObject($x) {
    var_dump($x instanceof object);
}
testInstanceofObject(new stdClass());


function testDollarDollarPlugin($a, $b = 'a') {
    var_dump($$b);
}
testDollarDollarPlugin(42);


function testDuplicateArrayKeyPlugin() {
    var_dump([
        '0' => 'b',
        0 => 'c',
    ]);
    var_dump([
        'key' => 'b',
        'c',
    ]);
}
testDuplicateArrayKeyPlugin();

function testNoopIsset($Foo) {
    var_dump(isset($foo));
}
testNoopIsset('key');

function testNonBoolBranchPlugin(array $args) {
    if ($args) {
        var_dump($args);
    }
}
testNonBoolBranchPlugin(['value']);

function testNonBoolInLogicalArithVisitor(array $args) {
    if (is_array($args) && $args) {
        var_dump($args);
    }
}
testNonBoolInLogicalArithVisitor(['value']);

function testNumericalEqualityPlugin() {
    var_dump('2e3' == '2000');
    var_dump('2e3' === '2000');  // this is fine
    var_dump(2.0 !== 2);
    var_dump(2.0 != 2);  // this is fine
}
testNumericalEqualityPlugin();

/** @suppress PhanParamTooFew - testing UnusedSuppressionPlugin */
function testUnusedSuppressionPlugin() {
    var_dump(intdiv(84, 2));
}
testUnusedSuppressionPlugin();

// Dead code detection should detect this
function testUnreferencedFunction() {}

// Dead code detection should not warn about built in error handlers
function __autoload($className) {}
// meaningless things with the same names
class __autoload {}
const __autoload = 3;
