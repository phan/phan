<?php

class StaticVisibility
{
    public static private(set) $value;

    public static function setValue(int $value): void
    {
        self::$value = $value;
    }
}

StaticVisibility::setValue(5);
echo StaticVisibility::$value;
