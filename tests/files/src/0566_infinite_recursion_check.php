<?php

function exampleInfiniteLoop(int $x) {
    echo $x;
    exampleInfiniteLoop($x + 1);
}
class InfiniteLoop {
    public function mymethod(int $x) {
        echo $x;
        $this->mymethod($x + 1);  // should warn
    }

    public function myStaticMethod(int $x) {
        self::myStaticMethod($x - 1);  // should warn
    }

    public static function getNextMultipleOf5(int $x) : int {
        if ($x % 5 === 0) {
            return $x;
        }
        return self::getNextMultipleOf5($x + 1);  // should not warn
    }

    public static function getNextMultipleOf3(int $x) : int {
        if ($x % 3 !== 0) {
            return self::getNextMultipleOf3($x + 1);  // should not warn
        }
        return $x;
    }

    public static function printNumbers(int $x) {
        if ($x % 2 !== 0) {
            echo "$x is odd\n";
        }
        self::printNumbers($x + 1);  // should warn
    }

    public static function foreachInfiniteLoop(int $x) : array {
        foreach (self::foreachInfiniteLoop($x) as $v) {  // should warn
            echo "Saw $v\n";
        }
        return [];
    }

    public static function foreachNotInfiniteLoop(array $elements) : array {
        foreach ($elements as $v) {
            self::foreachInfiniteLoop($v);  // should not warn
        }
        return [];
    }

    public static function whileInfiniteLoop() : bool {
        while (self::whileInfiniteLoop()) {  // should warn
            echo "not reachable\n";
        }
        return true;
    }

    public static function whileNotInfiniteLoop(int $depth) {
        while (rand(0, 2) == 0) {
            echo "at depth $depth\n";
            self::whileNotInfiniteLoop($depth + 1);  // should not warn
        }
    }

    public static function forInfiniteLoop(int $depth) {
        echo "At depth $depth\n";
        for (self::forInfiniteLoop($depth + 1); rand(0, 1); ) {  // should warn
            echo "unreachable\n";
        }
    }

    public static function forInfiniteLoop2(int $depth) : bool {
        echo "At depth $depth\n";
        for (; self::forInfiniteLoop2($depth + 1); ) {  // should warn
            echo "unreachable\n";
        }
        return false;
    }

    public static function forNotInfiniteLoop(int $depth) {
        //echo "At depth $depth\n";
        for (; rand(0, 1); ) {  // should warn
            self::forNotInfiniteLoop($depth + 1);  // should not warn
        }
    }

    public static function infiniteSwitch(int $depth) : int {
        echo "in $depth\n";
        switch (self::infiniteSwitch($depth)) {  // should warn
            // unreachable cases
        case 0:
            return 1;
        case 1:
            return 2;
        default:
            return -1;
        }
    }

    public static function finiteSwitch(int $depth) : int {
        echo "in $depth\n";
        switch ($depth) {
        case 0:
            return self::finiteSwitch($depth + 1);  // should not warn
        case 1:
            return 2;
        default:
            return -1;
        }
    }

    public static function unrelatedSwitch(int $depth) {
        switch ($depth) {
        case 0:
            echo "zero\n";
            break;
        case 1:
        default:
            echo "many\n";
        }
        self::unrelatedSwitch($depth + 1);  // should warn
    }
}
