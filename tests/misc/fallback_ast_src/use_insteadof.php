<?php
class C{
    use T, T2, T3 {
        T2::f2 insteadof T;
        T2::g insteadof T, T3;
    }
}
