<?php
function test52(int $intVar) {
    var_export(0 =& $intVar);  // should warn
    var_export(1 = $intVar);  // should warn
}
