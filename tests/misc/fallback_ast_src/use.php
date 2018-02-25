<?php

class C {
    use T, T2{
        f1 as private f2;
        T2::f2 insteadof T;
    }
}
