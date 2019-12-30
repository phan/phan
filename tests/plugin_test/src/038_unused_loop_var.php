<?php

class ExampleLoopUnused {
    public static function main()
    {
        $ast_items = [];
        $prev_was_element = false;
        foreach ([2,3] as $item) {
            if ($item > 2) {
                // should not warn about $prev_was_element being unused in subsequent definitions
                if (!$prev_was_element) {
                    $ast_items[] = null;  // warns because ast_items isn't used
                    continue;
                }
                '@phan-debug-var $prev_was_element';  // should be true before reassignment, not unknown
                $prev_was_element = false;
                $myUnusedVariable = new stdClass();
                continue;
            } else {
                $prev_was_element = true;
            }
        }
    }
}
ExampleLoopUnused::main();
