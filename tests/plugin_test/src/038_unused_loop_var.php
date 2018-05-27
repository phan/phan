<?php

class ExampleLoopUnused {
    public static function main()
    {
        $ast_items = [];
        $prev_was_element = false;
        foreach ([2,3] as $item) {
            if ($item > 2) {
                // @phan-suppress-next-line PhanPluginNonBoolBranch TODO: Investigate why
                if (!$prev_was_element) {
                    $ast_items[] = null;
                    continue;
                }
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
