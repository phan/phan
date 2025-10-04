<?php

class ParentNeverReturn
{
    protected static function terminate(): never
    {
        exit;
    }
}

class ChildNeverReturn extends ParentNeverReturn
{
    public static function viaParent(): never
    {
        parent::terminate();
    }

    public static function viaStatic(): never
    {
        static::terminate();
    }
}

if ((rand() % 2) > 0) {
    ChildNeverReturn::viaParent();
} else {
    ChildNeverReturn::viaStatic();
}
