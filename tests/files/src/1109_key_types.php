<?php
declare(strict_types=1);

class Test37
{
    /**
     * @param array<string,int> $foo TODO: Fix false positive PhanPluginUnknownArrayPropertyType
     */
    public function __construct(public array $foo) {}
}
$x = (new Test37(['key' => 'value']))->foo;
'@phan-debug-var $x';

class Invalid37
{
    public function __construct(public string $arg = 1) {}
}
var_export(new Invalid37('x'));
