<?php

class Common{
        const TYPE_INT = 'i';
}

class CC extends Common {
    protected static $mappings = [
        'key' => ['name' => 'name', 'type' => self::TYPE_INT],
    ];
    protected static $other_mappings = [
        'key' => ['name' => 'name', 'type' => self::TYPE_MISSING],
    ];
}

