<?php
function process_array_like(ArrayAccess&Traversable $param): ArrayAccess&Traversable {
    return $param;
}
class Example {
    public A&B $prop;
}
