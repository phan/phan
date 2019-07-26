<?php
class X704 {
    function test_array_object(callable $assert) {
        $assert($this);
        ASSERT($this);  // should be case-insensitive
    }
}
(new X704())->test_array_object('is_object');
