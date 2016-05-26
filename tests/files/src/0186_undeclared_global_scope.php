<?php

array_slice($undeclared, 0);

class C {
    function f() {
        array_slice($undeclared, 0);
    }
}
