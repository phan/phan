<?php
function test173($x) {
    try {
        throw new RuntimeException($x);
    } catch (RuntimeException $e) {
        echo "Caught $e\n";
    } catch (Error $e) {
        echo "Caught $e\n";
    }
}

function test_unserialize(string $s) {
    try {
        return unserialize($s);
    } catch (RuntimeException|InvalidArgumentException $e) {
    } catch (ArgumentCountError $e) {
    } catch (TypeError $_) {
    } catch (Error $_) {
    }
    if (isset($e)) {
        echo "Caught $e\n";
    }
}
test_unserialize('s:1:"c";');
test173('test');
