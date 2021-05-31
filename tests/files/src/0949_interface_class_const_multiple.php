<?php
namespace N949;

interface I1 {
    const X = 1;
}
interface I2 {
    const X = 2;
}

class C implements I1, I2 {
}
