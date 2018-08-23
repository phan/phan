#!/usr/bin/env php
<?php
/**
 * This checks that the function signatures are complete.
 * TODO: Expand to checking classes (methods, and properties)
 * TODO: Refactor the scripts in internal/ to reuse more code.
 */
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\UnionType;

function main_check_reflection_completeness()
{
    error_reporting(E_ALL);

    foreach (get_defined_functions() as $unused_ext => $group) {
        foreach ($group as $function_name) {
            if (!(new ReflectionFunction($function_name))->isInternal()) {
                continue;
            }
            $fqsen = FullyQualifiedFunctionName::fromFullyQualifiedString($function_name);
            $map_list = UnionType::internalFunctionSignatureMapForFQSEN($fqsen);
            if (!$map_list) {
                echo "Missing signatures for $function_name\n";
            }
        }
    }
}
main_check_reflection_completeness();
