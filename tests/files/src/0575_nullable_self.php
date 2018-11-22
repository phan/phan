<?php
namespace PhanBug;
trait Foo
{
    /** @var self|null */
    protected $parent;

    /** @var self[] */
    protected $other;

    public function setParent(self $parent = null)
    {
        $this->parent = $parent;
        $this->parent = null;
        $this->parent = $this;
        $this->parent = 2;
    }

    public function setOther(self $other)
    {
        $this->other = [$other];
        $this->other = [$this];
        $this->other = [null];
    }
}
