<?php

namespace NS5982;

/**
 * Taking a reference to an offset of a parameter passed by reference assigns nothing to what the parameter refers to:
 * the assignment is reported once, not a second time for the offset that the reference creates.
 *
 * @param array|object $category
 */
function compat(&$category): void {
    if (is_array($category) && isset($category['term_id'])) {
        $category['cat_ID'] = &$category['term_id'];
    }
}

class Query {
    /** @var ?\stdClass */
    public $obj;

    public function run(): void {
        if ($this->obj) {
            compat($this->obj);
        }
    }
}

/**
 * The reference turns null into an array, which is assigned to the property
 */
function createArray(&$value): void {
    $ref = &$value['k'];
    $ref = 1;
}

class Holder {
    /** @var ?int */
    public $count;

    public function run(): void {
        createArray($this->count);
    }
}
