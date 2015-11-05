<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\CodeBase;
use \Phan\Language\Element\Method;
use \Phan\Language\FQSEN;
use \Phan\Log;

trait DuplicateFunction {

    /**
     * Check to see if the given Clazz is a duplicate
     *
     * @return null
     */
    public static function analyzeDuplicateFunction(
        CodeBase $code_base,
        Method $method
    ) {
        $fqsen = $method->getFQSEN();

        if (!$fqsen->isAlternate()) {
            return;
        }

        $original_fqsen = $fqsen->getCanonicalFQSEN();

        if (!$code_base->hasMethodWithFQSEN($original_fqsen)) {
            // ...
            return;
        }

        $original_method =
            $code_base->getMethodByFQSEN($original_fqsen);

        if ('internal' === $original_method->getContext()->getFile()) {
            // If its in an conditional and the original is an
            // internal method, presume its all OK.
            if ($method->getContext()->getIsConditional()) {
                return;
            }

            // TODO: lowercasing so that we're compatible with the output we're
            //       comparing against. Feel free to not do this in the
            //       future.
            $method_name = strtolower($method->getName());

            Log::err(
                Log::EREDEF,
                "Function {$method_name} defined at {$method->getContext()->getFile()}:{$method->getContext()->getLineNumberStart()} was previously defined internally",
                $method->getContext()->getFile(),
                $method->getContext()->getLineNumberStart()
            );
        } else {
            Log::err(
                Log::EREDEF,
                "Function {$method_name} defined at {$method->getContext()->getFile()}:{$method->getContext()->getLineNumberStart()} was previously defined at {$original_method->getContext()->getFile()}:{$original_method->getContext()->getLineNumberStart()}",
                $method->getContext()->getFile(),
                $method->getContext()->getLineNumberStart()
            );
        }
    }

}
