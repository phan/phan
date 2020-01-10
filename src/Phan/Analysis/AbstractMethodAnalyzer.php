<?php

declare(strict_types=1);

namespace Phan\Analysis;

use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Method;

/**
 * This verifies that the inherited abstract methods are all implemented on non-abstract classes.
 * NOTE: This step must be run after adding methods from this class and each of its ancestors.
 */
class AbstractMethodAnalyzer
{

    /**
     * Check to see if signatures match
     */
    public static function analyzeAbstractMethodsAreImplemented(
        CodeBase $code_base,
        Clazz $class
    ): void {
        // Don't worry about internal classes
        if ($class->isPHPInternal()) {
            return;
        }
        // Don't worry about traits or abstract classes, those can have abstract methods
        if ($class->isAbstract() || $class->isTrait() || $class->isInterface()) {
            return;
        }
        foreach ($class->getMethodMap($code_base) as $method) {
            if ($method->isAbstract()) {
                if ($method->isPHPInternal()) {
                    Issue::maybeEmit(
                        $code_base,
                        $class->getContext(),
                        Issue::ClassContainsAbstractMethodInternal,
                        $class->getFileRef()->getLineNumberStart(),
                        (string)$class->getFQSEN(),
                        self::toRealSignature($method)
                    );
                } else {
                    Issue::maybeEmit(
                        $code_base,
                        $class->getContext(),
                        Issue::ClassContainsAbstractMethod,
                        $class->getFileRef()->getLineNumberStart(),
                        (string)$class->getFQSEN(),
                        self::toRealSignature($method),
                        $method->getFileRef()->getFile(),
                        $method->getFileRef()->getLineNumberStart()
                    );
                }
            }
        }
    }

    private static function toRealSignature(Method $method): string
    {
        $fqsen = $method->getDefiningFQSEN();
        $result = \sprintf(
            "%s::%s%s(%s)",
            (string) $fqsen->getFullyQualifiedClassName(),
            $method->returnsRef() ? '&' : '',
            $fqsen->getName(),
            $method->getRealParameterStubText()
        );
        $return_type = $method->getRealReturnType();
        if (!$return_type->isEmpty()) {
            $result .= ': ' . $return_type;
        }

        return $result;
    }
}
