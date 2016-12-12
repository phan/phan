<?php

class A {
    function one($a = 1) {}
}

class B extends A {
    function one($a = null) {}
}

class C extends A {
    function one($a = 'a') {}
}
