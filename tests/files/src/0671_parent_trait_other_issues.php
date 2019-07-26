<?php
namespace NS671;

// Phan should catch other issues when parent::class is used in a trait.
trait T {
    private static function expectIntString(int $a, string $x) {
        var_export([$a, $x]);
    }
    public static function main() {
        $x = parent::class;
        echo $x;
        $y = parent::SOME_CONST;
        echo $y;
        self::expectIntString(parent::class, true);
    }
}
class MyBaseClass {
    const SOME_CONST = 2;
}

class MySubClass extends MyBaseClass {
    use T;
};
MySubClass::main();
