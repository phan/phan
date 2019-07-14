<?php
function returnsPackedArgs($myVar, $myOtherVar, $myUnusedVar) {
    $nameToReturn = 'myOtherVar';
    return compact('myVar', [$nameToReturn]);
}
// returns ['myVar' => 'a', 'myOtherVar' => 'b']
var_export(returnsPackedArgs('a', 'b', 'c'));
