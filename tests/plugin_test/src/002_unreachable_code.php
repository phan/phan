<?php

function unreachableCode() {
    throw new \RuntimeException("Exception");
    return null;
}
try {
    unreachableCode();
} catch (Exception $e) {
}

class testUnreachableCode {
    public function __construct($x) {
        if (rand() % 2 > 0) {
            return;
        } else {
            if ($x > 0) {
                echo "It's positive";
                return;
            } else {
                exit(1);
            }
            echo "Testing";
        }
        echo "More statements\n";
        echo "Even more unreachable statements\n";
    }

    // Should warn about unreachable statement "Should not happen",
    // and should not warn about failing to return.
    public function unreachableSwitch(int $x) : bool {
        if (rand() % 2 > 0) {
            switch($x) {
            case 0:
            case 3:
            default:
                echo "anything\n";
                return false;
            }
            echo "Should not happen\n";
        } else {
            switch($x) {
            case 0:
            case 3:
            default:
                echo "not 2\n";
                return false;
            case 2:
                break;
            }
            return true;
        }
    }
}
$c = new testUnreachableCode(0);
$c->unreachableSwitch(4);

$d = new ReachableClass();
reachableFunction();
return;

class ReachableClass {
}
echo "Not reached\n";

function reachableFunction() {
}
