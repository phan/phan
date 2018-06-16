<?php

/** @var string $str */
$str = "string";
$char = $str{-1};

class class_008_parent {

    /** @var string  */
    private $parent_string = "string";

    public function getParentString() : string
    {
        return $this->parent_string;
    }

}

class class_008 extends class_008_parent {

    /** @var string */
    public $string;

    /** @var string */
    public static $static_string;

    /** @var string */
    const STR = "classconst";

    public function __construct()
    {
        $this->string = "string";
        self::$static_string = "string";
    }

    public function testStrCurlyBrackets(string $param1, string $param2) : string
    {
        $temp = $param1{-1};
        return $temp . $param2{"-2"};
    }

    /**
     * @return bool
     */
    public function testStrSquareBrackets() : bool
    {
        $var = self::STR["-3"];
        $var .= self::$static_string[-4];
        $var .= parent::getParentString()[-5];
        return $var && in_array($this->string[-5], [1, 2, 3]);
    }

    public function testArray(array $arr)
    {
        return $arr[-1];
    }

}

$c = new class_008();

$c->testStrCurlyBrackets("param1", "param2");
$c->testStrSquareBrackets();
$c->testArray([-1 => 1]);
