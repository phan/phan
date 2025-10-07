<?php

/**
 * Example function exercising invalid array generics.
 * @param array<int,string,string> $param
 * @suppress PhanPluginUnknownArrayFunctionParamType
 * @suppress PhanPluginUnknownArrayFunctionReturnType
 */
function takes_array_with_too_many_generics($param): array {
    return $param;
}
