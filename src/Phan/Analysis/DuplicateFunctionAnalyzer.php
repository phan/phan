<?php

declare(strict_types=1);

namespace Phan\Analysis;

use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Element\Flags;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;

/**
 * Checks to see if the given method is a duplicate of another method
 */
class DuplicateFunctionAnalyzer
{

    /**
     * Check to see if the given FunctionInterface is a duplicate
     */
    public static function analyzeDuplicateFunction(
        CodeBase $code_base,
        FunctionInterface $method
    ): void {
        $fqsen = $method->getFQSEN();

        if (!$fqsen->isAlternate()) {
            return;
        }

        $original_fqsen = $fqsen->getCanonicalFQSEN();

        if ($original_fqsen instanceof FullyQualifiedFunctionName) {
            if (!$code_base->hasFunctionWithFQSEN($original_fqsen)) {
                return;
            }

            $original_method = $code_base->getFunctionByFQSEN(
                $original_fqsen
            );
        } else {
            if (!$code_base->hasMethodWithFQSEN($original_fqsen)) {
                return;
            }

            $original_method = $code_base->getMethodByFQSEN($original_fqsen);
        }

        if ($original_fqsen instanceof FullyQualifiedFunctionName) {
            // Find the earlier declarations that this one conflicts with.
            // (`if (!function_exists('f')) { function f() {} }` does not redefine f())
            $conflicting_functions = [];
            for ($alternate_id = 0; $alternate_id < $fqsen->getAlternateId(); $alternate_id++) {
                $other_fqsen = $original_fqsen->withAlternateId($alternate_id);
                if (!$code_base->hasFunctionWithFQSEN($other_fqsen)) {
                    continue;
                }
                $other_function = $code_base->getFunctionByFQSEN($other_fqsen);
                if ($alternate_id > 0 && $other_function->isPHPInternal()) {
                    // Another signature of the internal function (e.g. session_set_cookie_params()), not another declaration.
                    continue;
                }
                if (!self::isGuardedPair($method, $other_function)) {
                    $conflicting_functions[] = $other_function;
                }
            }
            if (!$conflicting_functions) {
                return;
            }
            $original_method = $conflicting_functions[0];
            $is_first_conflict = \count($conflicting_functions) === 1;
        } else {
            $is_first_conflict = $fqsen->getAlternateId() === 1;
        }

        $method_name = $method->getName();

        if ($original_method->isPHPInternal()) {
            Issue::maybeEmit(
                $code_base,
                $method->getContext(),
                Issue::RedefineFunctionInternal,
                $method->getFileRef()->getLineNumberStart(),
                $method_name,
                $method->getFileRef()->getFile(),
                $method->getFileRef()->getLineNumberStart()
            );
        } else {
            Issue::maybeEmit(
                $code_base,
                $method->getContext(),
                Issue::RedefineFunction,
                $method->getFileRef()->getLineNumberStart(),
                $method_name,
                $method->getFileRef()->getFile(),
                $method->getFileRef()->getLineNumberStart(),
                $original_method->getFileRef()->getFile(),
                $original_method->getFileRef()->getLineNumberStart()
            );
            // If there are 3 functions with the same namespace and name,
            // warn *once* about the first functions being a duplicate.
            // NOTE: This won't work very well in language server mode.
            if ($is_first_conflict) {
                Issue::maybeEmit(
                    $code_base,
                    $original_method->getContext(),
                    Issue::RedefineFunction,
                    $original_method->getFileRef()->getLineNumberStart(),
                    $original_method->getName(),
                    $original_method->getFileRef()->getFile(),
                    $original_method->getFileRef()->getLineNumberStart(),
                    $method->getFileRef()->getFile(),
                    $method->getFileRef()->getLineNumberStart()
                );
            }
        }
    }

    /**
     * @return bool whether one of the functions is declared in `if (!function_exists('name'))`, so that only one of the two gets declared.
     * A pair that takes a different number of parameters is still reported: calls are only checked against one of them.
     */
    private static function isGuardedPair(FunctionInterface $a, FunctionInterface $b): bool
    {
        return (($a->getPhanFlags() | $b->getPhanFlags()) & Flags::IS_GUARDED_DECLARATION) !== 0
            && $a->getNumberOfRequiredParameters() === $b->getNumberOfRequiredParameters()
            && $a->getNumberOfParameters() === $b->getNumberOfParameters();
    }
}
