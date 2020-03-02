<?php
// When allow_overriding_vague_return_types is enabled, phan will add the observed return types to the function return type, for types such as object/mixed.
/**
 * @return object
 */
function returns_object4() {
    return new stdClass();
}
returns_object4()->missingMethod();
