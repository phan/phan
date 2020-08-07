
<?php
class C24 {
    public static function main(int $requiredInt, string $requiredString, bool $optionalFlag = false, mixed $other = null) {
        echo json_encode([$requiredString, $requiredInt, $optionalFlag, $other]), "\n";
    }
}
call_user_func('C24::main', other: true);
call_user_func('C24::main', 1, optionalFlag: true);
call_user_func('C24::main', 1, optionalFlag: true, other: 123);
call_user_func('C24::main', requiredInt: 0, optionalFlag: true, other: 123);
call_user_func('C24::main', requiredString: 0, optionalFlag: true, other: 123);
echo call_user_func('strlen', definitelyInvalidFlag: 'value');
echo call_user_func('intdiv', dividend: 1000, divisor: 123);
echo call_user_func('intdiv', divisor: 123);
