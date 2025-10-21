<?php
class X {
    #[namespace\A2]
    private $x;
    #[X]
    /** Comment */
    #[Yyz(1+1),]
    const MY_CONST = 123;
}
