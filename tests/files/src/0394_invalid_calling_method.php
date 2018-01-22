<?php
class Test394 {
    public static function callMethod() {
        echo "Called\n";
    }
}
$str = 'Test394';  // Phan isn't aware of **values** of strings right now.
$str::callMethod();  // Should not warn
$str->callMethod();  // Should warn, guaranteed to be invalid
$x = ['Test394'];
$x->callMethod();  // Should warn
