<?php declare(strict_types=1);

function expect_string_298(string $x) {}
function expect_int_298(int $x) {}

function test298() {
    expect_string_298("0" + "1");  // warn
    expect_string_298("0" | "1");  // warn
    expect_string_298("0" ^ "\x01");  // Becomes a string
    expect_int_298("0" ^ "\x01");  // warn, Becomes a string
    expect_string_298(1 ^ 2);  // warn
    expect_string_298(1 xor 2);  // warn
    expect_int_298("0" || "1");  // warn
    expect_int_298("0" or "1");  // warn
    expect_int_298("0" . "1");  // warn
    expect_string_298(4 / 2);  // warn
    expect_string_298(4 == 2);  // warn
    expect_string_298(4 === 2);  // warn
    expect_string_298(4 != 2);  // warn
    expect_string_298(4 !== 2);  // warn
    expect_string_298(4 < 2);  // warn
    expect_string_298(4 <= 2);  // warn
    expect_string_298(4 * 7);  // warn
    expect_string_298(4 % 7);  // warn
    expect_string_298(4 ** 7);  // warn, pow
    expect_string_298(4 << 1);  // warn
    expect_string_298(4 >> 1);  // warn
    expect_string_298(4 <=> 2);  // warn
    expect_int_298(4 <=> 2);  // good
    expect_string_298(4 - 2);  // warn
    expect_string_298(4 & 7);  // warn
    expect_string_298(4 | 7);  // warn
    expect_string_298(4 ?? 7);  // warn
    expect_string_298(4 > 7);  // warn
    expect_string_298(4 >= 7);  // warn

    $strVar = 'x';
    $intVar = 42;
    expect_int_298($strVar .= 'suffix');
    expect_string_298($intVar ^= 2);  // warn, this is an int
    expect_int_298($strVar ^= "\x01\x02");  // warn, this is a string
    expect_string_298($intVar |= 1);
    expect_string_298($intVar ^= 2);
    expect_string_298($intVar &= 0xffff);
    expect_string_298($intVar /= 2);
    expect_string_298($intVar *= 2);
    expect_string_298($intVar **= 2);
    expect_string_298($intVar %= 5);
    expect_string_298($intVar += 2);
    expect_string_298($intVar -= 5);
    expect_string_298($intVar <<= 1);
    expect_string_298($intVar >>= 1);
}
