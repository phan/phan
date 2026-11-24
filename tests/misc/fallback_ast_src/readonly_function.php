<?php
function readonly() {
    return 1;
}
echo readonly();
$x = readonly() + readonly(
    2
);
class ReadonlyMethod {
    function readonly() {}
    function f() {
        return $this->readonly() + self::readonly();
    }
}
$n = new (readonly())();
$m = namespace\readonly();
