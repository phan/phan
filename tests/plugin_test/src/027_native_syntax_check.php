<?php
class X{
    public $y = 2;
    public $x = $y;  // Not a *semantically* valid default, but parseable by php-ast
}
