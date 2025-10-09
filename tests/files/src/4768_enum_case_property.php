<?php
class DecimalEntryFieldValue {}

enum Issue4768String: string
{
    case DECIMAL = 'decimal';

    public const MAP = [
        self::DECIMAL->value => DecimalEntryFieldValue::class,
    ];

    public const NAMES = [
        self::DECIMAL->name,
    ];
}
