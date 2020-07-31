<?php
/** @template T */
class C189 {
    /** @var MY_CONST bad type */
    const MY_CONST = true;
    /**
     * @var iterable<T>
     */
    const INVALID_ITERABLE_CONST = [[]];
    /**
     * @var iterable<Traversable>
     */
    const INVALID_TRAVERSABLE_CONST = [[]];
}
if (C189::MY_CONST) {
    echo "Redundant\n";
}
var_export(C189::INVALID_ITERABLE_CONST);
var_export(C189::INVALID_TRAVERSABLE_CONST);
