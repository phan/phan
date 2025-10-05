<?php
// Test typed class constants (https://wiki.php.net/rfc/typed_class_constants)
class C0 {
    public const int x = 123;
    public const int bad = 'x';  // Type mismatch
    public const never more = 'x'; // Never type is always invalid
}
echo count(C0::x);
echo count(C0::bad);
echo count(C0::more);
