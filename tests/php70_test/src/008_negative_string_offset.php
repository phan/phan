<?php

$str = "abcdef";

$char = $str{-1};

class class_008 {

    public function method_1($param1, $param2)
    {
        return $param1{-2} . $param2{-3};
    }

    public function method_2($param)
    {
        return in_array($param[-4], [1, 2, 3]);
    }

}

$c = new class_008();
$c->method_1("param1", "param2");
$c->method_2("param");