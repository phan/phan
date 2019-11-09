<?php
namespace NS819;

class Foo extends \stdClass
{
    /** @var string */
    public $name = '';
}

class Bar extends Foo {

}


$test = new Bar();
$test->name = 'John';
$test->age = 30;
$test->missingMethod();
