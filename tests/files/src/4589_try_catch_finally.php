<?php
function test_issue_4589(string $path) {
    $threw = null;
    try {
        return require $path;
    } catch (\Throwable $e) {
        $threw = $e;
    } finally {
        if ($threw) {
            echo "exception handled\n";
        }
    }
}
