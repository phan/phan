<?php
// FIXME TODO support https://wiki.php.net/rfc/typed_class_constants
class C0 {
    // FIXME update php-ast and tolerant-php-parser fallback
    public const int x = 123;
    public const int bad = 'x';
    public const never more = 'x'; // should always warn
}
echo count(C0::x);
echo count(C0::bad);
echo count(C0::more);
