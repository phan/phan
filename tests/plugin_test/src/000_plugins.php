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
    var_dump(isset($$Foo));  // should not crash
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

function missingReturnType(?int $x) : int {
    if (is_int($x)) {
        return $x;
    }
}
missingReturnType(2);

class ReturnChecks {
    public static function missingReturnTypeSwitch(int $x) : int {
        switch($x) {
        case 2:
            throw new \RuntimeException("saw 2");
        case 3:
            break;
        default:
            return $x ?? 3;
        }
    }

    public static function missingReturnTypeSwitchGood(int $x) : int {
        switch($x) {
        case 2:
            return 4;
        case 3:
            throw new \RuntimeException("saw 3");
        default:
            return $x ?? 3;
        }
    }

    // should not falsely detect as missing a return type
    public static function generator(int $x) : Traversable {
        if ($x > 0) {
            if (rand() % 2 > 0) {
                yield from self::otherGenerator();
            }
        }
    }

    /**
     * @return iterable
     */
    public static function otherGenerator() {
        // should not falsely detect as missing a return type
        echo "In generator\n";
        $x = yield 2;
    }

    /**
     * @param int[]|string[] $x
     */
    public static function skippingWithBreak($x) {
        // should not falsely detect as missing a return type
        for ($i = 0; $i < count($x); $i++) {
            $a = $x[$i];
            if (!is_int($a)) {
                break;
            }
            echo strlen($a);  // Phan should warn about $a being an int.
        }
    }
}
ReturnChecks::missingReturnTypeSwitchGood(3);
ReturnChecks::missingReturnTypeSwitch(5);
ReturnChecks::generator(3);
ReturnChecks::skippingWithBreak([3, 4, "strval"]);
