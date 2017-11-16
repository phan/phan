<?php
// Phan's DuplicateKeyPlugin can analyze switch statements for duplicate cases, similar to the way duplicate keys are analyzed.

const Z = 1;
const Y = 1;
const A = true;
const B = false;

class NSwitch {
    const XYZ = 1;
    const TRUE = true;
    const FALSE = false;
}

$x = rand() % 10;
switch($x) {
case Y: return 1;
case Z: return 2;
case NSwitch::XYZ: return 3;
case NSwitch::TRUE: return true;
case NSwitch::FALSE: return false;
case A: return true;
case B: return false;
case false: return 11;
case true: return 13;
case null: return 14;
case '': return 15;
case '(test literal)': return 15;
case '(test literal)': return 16;
// Could analyze these later, but not doing that yet. For now, just check that this doesn't warn.
case __FILE__: return 15;
case __LINE__: return 15;
default: return 'default';
};
