<?php
class MyClass376
{
    /**
     * @return ?self
     */
    public function getParent(bool $hasParent)
    {
        if (!$hasParent) {
           return null;
        }

        return new MyClass376();
    }
}
