<?php

class C {
    public int $existing = 1;

    function test() {
        // These should NOT warn - isset/empty are designed to check existence
        if (isset($this->undefined)) echo "A\n";
        if (empty($this->undefined)) echo "B\n";

        // This SHOULD warn - normal property access outside isset/empty
        echo $this->undefined;

        // These should NOT warn - checking variables in isset/empty
        if (isset($undefined_var)) echo "C\n";
        if (empty($undefined_var)) echo "D\n";

        // This SHOULD warn - using variable outside isset/empty
        echo $undefined_var2;
    }
}
