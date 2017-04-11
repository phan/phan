<?php

const FEATURE_FLAG = true;

// A literal (instead of AST node) used in a conditional should not cause crashes.
function check_literal_conditional() {
    $x = 3;
    intdiv(0 ? $x : 2, 2);
    intdiv(0 ?: $x, 2);
    intdiv(0 ?: $x[0], 2);
    intdiv(1 ?: $x[0], 2);  // should warn about $x[0] being invalid, even if it's dead code?
    intdiv(true ?: $x, 2);  // should warn about passing bool to intdiv
    intdiv(\true ?: $x, 2);  // should warn about passing bool to intdiv
    intdiv(false ?: $x, 2);  // should not warn
    intdiv(\false ?: $x, 2);  // should not warn
    intdiv(false ? true : $x, 2);  // should not warn
    intdiv(TRUE ? true : $x, 2);  // should warn
    intdiv('key' ?: $x, 2);  // should warn about passing string
    intdiv('1' ?: $x, 2);  // should warn about passing string (or only in strict mode?)
    intdiv('' ?: $x, 2);  // should not warn
    intdiv('0' ?: $x, 2);  // should not warn
    intdiv(4.2 ?: $x, 2);  // should warn about passing float
    intdiv(0.0 ?: $x, 2);  // should not warn about passing float (still not great code)
    intdiv(FEATURE_FLAG ? 'value' : $x, 2);  // In the **general** case, we're not sure about internal or external constants, so we assume both types are possible.
    intdiv(FEATURE_FLAG ? $x : 'value', 2);  // also test the other way around.
}
