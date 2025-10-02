<?php

class Example742 {
    public int $intVal = 2;
    public string $strVal;
    public string $incompatibleVal = 2;  // should warn
}
$foo = new Example742();
$foo->intVal = 'bar';  // should warn
$foo->strVal = 'bar';
$foo->strVal[0] = 'x';
$foo->strVal = ['x'];
$foo->strVal[0] = [];  // should warn
$foo->strVal[0] = [];  // should warn again
$foo->incompatibleVal = 'bar';
$foo->incompatibleVal = 2;  // should warn
