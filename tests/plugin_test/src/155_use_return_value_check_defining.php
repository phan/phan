<?php
function show_runtime_exception(ReflectionException $e) : void {
    $e->getCode();  // should warn about unused method (getCode() is final)
}

