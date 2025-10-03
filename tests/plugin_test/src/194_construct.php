<?php
namespace N194;

class SubOption
{
    public int $subSubOption;
}

class Options
{
    public SubOption $subOption;

    public function __construct()
    {
        $this->subOption = new SubOption();
    }
}

abstract class A
{
}

class B extends A
{
    protected Options $options;

    public function __construct()
    {
        $this->options = new Options();
    }
}

class C extends B
{
    public function __construct()
    {
        parent::__construct();
        $this->options->subOption->subSubOption = 123;
    }
}
