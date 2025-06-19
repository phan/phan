<?php
// support https://wiki.php.net/rfc/dynamic_class_constant_fetch
class C5 {
    const MY_CONST = 'bar';
}
$constName = 'MY_CONST';
echo C5::MY_CONST;
echo C5::{$constName};
echo C5::{[]};
echo C5::{null};
$constName = 'MY_CONST_' . rand();
echo C5::{$constName};
$constName = 123;
echo C5::{$constName};

enum E5: string {
    case MY_ENUM = 'bar';
}
$enumName = 'MY_ENUM';
echo E5::MY_ENUM->value;
echo E5::{$enumName}->value;
echo E5::{[]}->value;
echo E5::{null}->value;
$enumName = 'MY_ENUM_' . rand();
echo E5::{$enumName}->value;
$enumName = 123;
echo E5::{$enumName}->value;