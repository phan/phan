<?php
class MyClass376
{
    /**
     * @return ?self
     */
    public function getParent(bool $hasParent) : ?self
    {
        if (!$hasParent) {
           return null;
        }

        return new MyClass376();
    }

    /**
     * @return ?self
     */
    public function test(bool $hasParent)
    {
        if (!$hasParent) {
           return new stdClass();
        }
        return new MyClass376();
    }
}
