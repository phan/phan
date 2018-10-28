<?php
declare(strict_types=1);

class SomeClass
{
    use SomeTrait;

    protected $param;

    /**
     * Set some param
     *
     * @param string $param
     *
     * @return $this
     */
    public function setSomeParam($param)
    {
        $this->param = $param;
        return $this;
    }
}

trait SomeTrait
{
    protected $traitParam;

    /**
     * Get some param
     *
     * @param string $traitParam
     *
     * @return self
     */
    public function setSomeTraitParam($traitParam) : self
    {
        printf('Class: %-13s | get_class(): %-13s | get_called_class(): %-13s%s', __CLASS__, get_class(), get_called_class(), PHP_EOL);
        $this->traitParam = $traitParam;
        return $this;
    }
}

$obj = new SomeClass();

$obj->setSomeParam('some value')
    ->setSomeTraitParam('some value')
    ->setSomeParam('some value');
