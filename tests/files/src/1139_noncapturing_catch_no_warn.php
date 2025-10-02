<?php
function example13(string $c): bool {
    try {
        $c($c);
        return true;
    } catch (RuntimeException|Error) { // Because minimum_target_php_version >= 8.0, Phan does not warn about non-capturing catches.
        return false;
    }
}
example13('var_dump');
