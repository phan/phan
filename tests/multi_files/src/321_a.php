<?php
namespace Foo\Api;
class Api
{
    public function getName()
    {
        return get_class($this);
    }
}
