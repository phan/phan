<?php
namespace NSRedundant;

function test($a, $b) {
    var_export((bool)($a < $b));
    var_export((bool)($a <= $b));
    var_export((bool)($a > $b));
    var_export((bool)($a >= $b));
    var_export((bool)($a == $b));
    var_export((bool)($a === $b));
    var_export((bool)($a != $b));
    var_export((bool)($a <> $b));
    var_export((bool)($a !== $b));
    var_export((bool)!$a);
    var_export((int)($a <=> $b));
    var_export((bool)($a xor $b));
    var_export((bool)($a and $b));
    var_export((bool)($a or $b));
    var_export((bool)($a && $b));
    var_export((bool)($a || $b));
}
function testops($a, $b) {
    var_export((bool)(bool)$a);
    //  ++ -- ~ (int) (float) (string) (array) (object) (bool) @
    var_export((int)(int)$a);
    var_export((float)(float)$a);
    var_export((int)(int)$a);
    var_export((array)(array)$a);
    var_export((object)(object)$a);
    var_export((float)($a * $b));
    var_export((float)($a / $b));
    var_export((float)($a % $b));
    var_export((float)($a + $b));
    var_export((float)($a - $b));
    var_export((string)($a . $b));
    var_export((float)($a . $b));  // not redundant
    var_export((int)($a >> $b));
    var_export((int)($a << $b));
    var_export((int)(5 << 1));
    var_export((int)(5 >> 1));
    var_export((array)($a & $b));  // impossible. TODO: Warn about impossible casts.
    // can be string/int, so not redundant
    var_export((int)($a & $b));  // not redundant
    var_export((int)($a ^ $b));  // can be string/int
    var_export((int)($a | $b));  // not redundant

    var_export((int)(3 & 6));
    var_export((int)(3 ^ 6));
    var_export((int)(3 | 6));
    $y = 0;
    $x = 2;
    var_export((float)($x *= 3));
    $y += $x;
    $x = 2.5;
    var_export((float)($x += 3));  // TODO: Be more precise about += when both sides have known real types.
    $y += $x;
    $x = 2.5;
    var_export((float)($x -= 3));
    $y += $x;
    var_export((float)($x **= 3));
    $y += $x;
    $x = 2;
    var_export((float)($x /= 3));
    $y += $x;
    $x = 2;
    var_export((int)($x %= 3));
    $y += $x;
    $x = 'prefix';
    var_export((string)($x .= 'suffix'));
    $y += strlen($x);

    $x = 124652;
    var_export((int)($x <<= 3));
    $y += $x;

    $x = 124652;
    var_export((int)($x >>= 3));
    $y += $x;

    // bitwise operators can act on int/string. TODO: Be more precise for ^=, etc. when real types are known.
    $x = 124652;
    var_export((int)($x ^= 3));
    $y += $x;

    $x = 124652;
    var_export((int)($x |= 3));
    $y += $x;

    $x = 124652;
    var_export((int)($x &= 3));
    $y += $x;
    var_export($y);
}
