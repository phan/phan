<?php

class class_008 {

    public function testStrCurlyBrackets(string $param1, string $param2) : string
    {
        $temp = $param1{-2};
        return $temp . $param2{"-3"};
    }

    /**
     * @param string $param
     * @return bool
     */
    public function testStrSquareBrackets(string $param) : bool
    {
        $one = $param["-1"];
        return $one || in_array($param[-4], [1, 2, 3]);
    }

    public function testArray(array $array)
    {
        return $array[-1];
    }

}

$c = new class_008();

$c->testStrCurlyBrackets("param1", "param2");
$c->testStrSquareBrackets("param");
$c->testArray([-1 => 1]);
