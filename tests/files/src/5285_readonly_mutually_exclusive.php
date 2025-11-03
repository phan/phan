<?php

// Test 1: Valid - if/else branches (should NOT warn)
class Test1_IfElse {
    private readonly int $prop;

    public function __construct() {
        if (rand()) {
            $this->prop = 777;
        } else {
            $this->prop = 42;
        }
    }
}

// Test 2: Valid - switch/case branches (should NOT warn)
class Test2_Switch {
    private readonly int $prop;

    public function __construct(int $value) {
        switch ($value) {
            case 1:
                $this->prop = 100;
                break;
            case 2:
                $this->prop = 200;
                break;
            default:
                $this->prop = 300;
                break;
        }
    }
}

// Test 3: Invalid - sequential assignments (SHOULD warn)
class Test3_Sequential {
    private readonly int $prop;

    public function __construct() {
        $this->prop = 1;
        $this->prop = 2;  // Should warn: AccessReadOnlyPropertyMultipleTimes
    }
}

// Test 4: Invalid - multiple assignments in same if branch (SHOULD warn)
class Test4_SameBranch {
    private readonly int $prop;

    public function __construct() {
        if (rand()) {
            $this->prop = 1;
            $this->prop = 2;  // Should warn: AccessReadOnlyPropertyMultipleTimes
        }
    }
}

// Test 5: Valid - multiple if statements (should NOT warn, they're mutually exclusive)
class Test5_MultipleIfs {
    private readonly int $prop;

    public function __construct() {
        if (rand() < 0.5) {
            $this->prop = 1;
        } elseif (rand() < 0.75) {
            $this->prop = 2;
        } else {
            $this->prop = 3;
        }
    }
}

// Test 6: Valid - ternary assignment (should NOT warn)
class Test6_Ternary {
    private readonly int $prop;

    public function __construct() {
        $this->prop = rand() ? 1 : 2;
    }
}

// Test 7: Invalid - assignment in if and after if (SHOULD warn)
class Test7_IfAndAfter {
    private readonly int $prop;

    public function __construct() {
        if (rand()) {
            $this->prop = 1;
        }
        $this->prop = 2;  // Should warn: AccessReadOnlyPropertyMultipleTimes
    }
}

// Test 8: Valid - nested if/else (should NOT warn if properly exclusive)
class Test8_NestedIf {
    private readonly int $prop;

    public function __construct() {
        if (rand()) {
            if (rand()) {
                $this->prop = 1;
            } else {
                $this->prop = 2;
            }
        } else {
            $this->prop = 3;
        }
    }
}

// Test 9: Invalid - assignment before and in if (SHOULD warn)
class Test9_BeforeAndInIf {
    private readonly int $prop;

    public function __construct() {
        $this->prop = 1;
        if (rand()) {
            $this->prop = 2;  // Should warn: AccessReadOnlyPropertyMultipleTimes
        }
    }
}

// Test 10: Valid - complex switch with different cases (should NOT warn)
class Test10_ComplexSwitch {
    private readonly string $prop;

    public function __construct(string $mode) {
        switch ($mode) {
            case 'a':
                $this->prop = 'alpha';
                break;
            case 'b':
                $this->prop = 'beta';
                break;
            case 'g':
                $this->prop = 'gamma';
                break;
            default:
                $this->prop = 'delta';
                break;
        }
    }
}

// Test 11: Invalid - switch case fallthrough (SHOULD warn)
class Test11_SwitchFallthrough {
    private readonly int $prop;

    public function __construct(int $value) {
        switch ($value) {
            case 1:
                $this->prop = 100;
                // No break - falls through!
            case 2:
                $this->prop = 200;  // Should warn: AccessReadOnlyPropertyMultipleTimes
                break;
        }
    }
}

// Test 12: Invalid - if/else inside while loop (SHOULD warn)
class Test12_IfElseInLoop {
    private readonly int $prop;

    public function __construct(array $values) {
        foreach ($values as $value) {
            if ($value > 0) {
                $this->prop = 1;  // Should warn: AccessReadOnlyPropertyMultipleTimes
            } else {
                $this->prop = 2;
            }
        }
    }
}

// Test 13: Invalid - switch inside for loop (SHOULD warn)
class Test13_SwitchInLoop {
    private readonly string $prop;

    public function __construct(array $items) {
        for ($i = 0; $i < count($items); $i++) {
            switch ($items[$i]) {
                case 'a':
                    $this->prop = 'alpha';  // Should warn: AccessReadOnlyPropertyMultipleTimes
                    break;
                case 'b':
                    $this->prop = 'beta';
                    break;
            }
        }
    }
}

// Test 14: Invalid - nested if in while loop (SHOULD warn)
class Test14_NestedIfInWhileLoop {
    private readonly int $prop;

    public function __construct() {
        $i = 0;
        while ($i < 10) {
            if (rand()) {
                $this->prop = 100;  // Should warn: AccessReadOnlyPropertyMultipleTimes
            } else {
                $this->prop = 200;
            }
            $i++;
        }
    }
}
