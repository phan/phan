<?php
function test904(Exception $e) {
    throw new AssertionError("Test {$e->getMessage()}", $e);
}
