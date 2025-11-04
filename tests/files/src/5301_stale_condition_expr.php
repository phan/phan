<?php
// Test case for issue #5301: Stale condition expressions causing false positive warnings

function test_issue_5301(): void {
    // Original issue code from #5301
    $data = new stdClass();
    $isObj = is_object($data);

    if ($isObj) {
        // At this point, $data is correctly narrowed to object
    }

    // Reassign $data to an array
    $data = ['foo'];

    if ($isObj) {
        // Before the fix: Phan incorrectly narrows $data to object due to the stale
        // condition expression, causing a false positive "redundant cast" warning
        // After the fix: The condition expression is invalidated when $data is reassigned,
        // so no type narrowing happens and no warning is emitted
        $casted = (object)$data;
    }
}
