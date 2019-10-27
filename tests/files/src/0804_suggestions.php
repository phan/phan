<?php

class Shop804 {
    public static function buyCheese(string $name) : string {
        return "No";
    }

    public static $LIST_OF_CHEESES = [];
}
// Should suggest static methods and static properties.
var_export(Shop804::LIST_OF_CHEESES);
echo Shop804::buyCheese;
