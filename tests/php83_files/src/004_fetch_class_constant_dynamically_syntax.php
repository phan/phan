<?php
// support https://wiki.php.net/rfc/dynamic_class_constant_fetch
class C5 {
    const string MY_CONST = 'bar';
}
enum E5: string {
    case MY_ENUM = 'bar';
}
$constName = 'MY_CONST';
$enumName = 'MY_ENUM';
echo C5::MY_CONST;
echo C5::{$constName};
echo C5::{[]};
echo C5::{null};
echo E5::MY_ENUM->value;
echo E5::{$enumName}->value;
echo E5::{[]}->value;
echo E5::{null}->value;
