<?php
#[Attribute(Attribute::TARGET_FUNCTION | intdiv(2, 1))]
class X36 {
}

// PHP 8.0 attributes do not support named arguments
#[Attribute(flags: Attribute::TARGET_FUNCTION)]
class Y36 {
}
