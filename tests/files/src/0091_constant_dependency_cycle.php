<?php

class A {
    const C1 = B::C2;
}

class B {
    const C2 = A::C1;
}

print B::C2;
