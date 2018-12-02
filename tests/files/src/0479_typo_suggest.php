<?php

function suggest_undeclared_variable($param1, $param2) {
    $myValue = 2;
    $x = $Param2;  // should make case-insensitive suggestions

    $y = $my_value;  // should suggest changes with low levenstein distances

    $z = $notDeclaredAnywhere;

    $w = $PARAM;
}

class ExampleSuggest {
    public $_prop = 'x';
    public function example() {
        $x = $_prop;
        $prop = 2;
        $y = $_prop;
        $my_really_longname = 2;
        echo $myReallyLongname;
    }
}
