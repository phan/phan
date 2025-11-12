<?php

// Test for issue #4926: False positive when assigning to different fields
// of arrays with mixed type (e.g., from json_decode)

class TestMixedArrayAccess {
    public function testForeach(string $json) {
        $arr = json_decode($json, true);
        foreach ($arr as $row) {
            // Both assignments should work without warnings
            $row["x"] = strlen($row["x"]);
            $row["y"] = strlen($row["y"]);
        }
    }

    public function testWithAnnotation(string $json) {
        // With explicit type annotation, should work correctly
        $arr = json_decode($json, true);
        '@phan-var list<array{x:string,y:string}> $arr';
        foreach ($arr as $row) {
            $row["x"] = strlen($row["x"]);
            $row["y"] = strlen($row["y"]);
        }
    }
}
