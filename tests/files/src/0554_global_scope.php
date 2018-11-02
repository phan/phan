<?php
$strCommand = array_shift( $argv );
echo "Command: ", $strCommand, "\n";
$strCommand = array_shift( $_GET );
echo "Command: ", $strCommand, "\n";

call_user_func (function () {
    $strCommand = array_shift( $undef );  // should warn
    echo "Command: ", $strCommand, "\n";
    $strCommand = array_shift( $argv );  // should warn, this is a global but is not included in this scope.
    echo "Command: ", $strCommand, "\n";
    $strCommand = array_shift( $_GET );
    echo "Command: ", $strCommand, "\n";
    $argv = ['fake'];
    $strCommand = array_shift( $argv );  // should not warn, this is a local variable
    echo "Command: ", $strCommand, "\n";
    $argv = 2;
    $strCommand = array_shift( $argv );  // should warn, this is a local variable of the wrong type
    echo "Command: ", $strCommand, "\n";
});

call_user_func (function () {
    global $argv;
    $strCommand = array_shift( $argv );  // should not warn, this is an included global variable
    echo "Command: ", $strCommand, "\n";
    global $argc;
    $strCommand = array_shift( $argc );  // should warn, this is an included global variable of the wrong type
    echo "Command: ", $strCommand, "\n";
});
