<?php

class A295 {
    use T1, T2 {
        T1::foo as ::xyz insteadof T2;  // This is invalid, but parser invariants should hold
    }
}
