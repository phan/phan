<?php
function getRef(&$var) { }

function &returnRef1() {
    $a = 5;
    return $a;
}
function returnNonRef1() {
    $a = 5;
    return $a;
}
class ARefTest{
    static function &returnRef2()  {
        $a = 5;
        return $a;
    }
    static function returnNonRef2()  {
        $a = 5;
        return $a;
    }
    function &returnRef3()  {
        $a = 5;
        return $a;
    }
    function returnNonRef3()  {
        $a = 5;
        return $a;
    }
}

getRef(returnRef1());
getRef(returnNonRef1());
getRef(ARefTest::returnRef2());
getRef(ARefTest::returnNonRef2());
getRef((new ARefTest())->returnRef3());
getRef((new ARefTest())->returnNonRef3());
