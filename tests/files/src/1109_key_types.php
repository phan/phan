<?php
declare(strict_types=1);

class Test1109
{
    /**
     * @param array<string,int> $foo
     */
    public function __construct(public array $foo) {}
}
$x = (new Test1109(['key' => 'value']))->foo;
'@phan-debug-var $x';

class Invalid1109
{
    public function __construct(public string $arg = 1) {}
}
var_export(new Invalid1109('x'));
