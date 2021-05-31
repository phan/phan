<?php

namespace NS950;

interface I1 {
    const A = 1;
}

interface I2 extends I1 {
}

class X implements I1, I2 { // This is allowed
}

interface I3 extends I2 {
    const A = 1;  // This is forbidden
}
var_dump(new X());
