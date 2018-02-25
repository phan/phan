<?php
class C{
    use T, T2 {
        T2::f2 insteadof T;
    }
}
