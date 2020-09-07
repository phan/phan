<?php

declare(strict_types=1);

namespace Phan\Analysis;

use Phan\Language\Element\AddressableElementInterface;
use Phan\Language\Element\Attribute;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Parameter;
use Phan\CodeBase;
use Phan\Issue;

/**
 * Analyzer of the attributes of declarations.
 * Emits warnings, and will eventually modify the way the element is analyzed.
 */
class AttributeAnalyzer
{
    /**
     * Check function, closure, and method parameters to make sure they're valid
     *
     * This will also warn if method parameters are incompatible with the parameters of ancestor methods.
     */
    public static function analyzeAttributesOfFunctionInterface(
        CodeBase $code_base,
        FunctionInterface $method
    ): void {
        foreach ($method->getAttributeList() as $attribute) {
            // TODO: Check if they're on the same line
            self::checkAttribute($code_base, $method, $method, $attribute);
        }
        foreach ($method->getRealParameterList() as $parameter) {
            foreach ($parameter->getAttributeList() as $attribute) {
                self::checkAttribute($code_base, $method, $parameter, $attribute);
                // TODO: Check if they're on the same line
            }
        }
    }

    /**
     * @param FunctionInterface|Parameter $element @phan-unused-param
     */
    private static function checkAttribute(
        CodeBase $code_base,
        AddressableElementInterface $declaration,
        $element,
        Attribute $attribute
    ): void {
        $fqsen = $attribute->getFQSEN();
        if ($code_base->hasClassWithFQSEN($fqsen)) {
            $class = $code_base->getClassByFQSEN($fqsen);
            if ($class->isClass() && !$class->isAbstract()) {
                // TODO: Release php-ast bugfix
                /*
                if (!$class->isAttribute()) {
                    Issue::maybeEmit(
                        $code_base,
                        $declaration->getContext(),
                        Issue::AttributeNonAttribute,
                        $attribute->getLineno(),
                        $fqsen,
                        '#[Attribute(...)]'
                    );
                }
                 */
                // TODO: Pass this to the method call analyzer?
                //$method = $class->getMethodByName($code_base, '__construct');
            } else {
                Issue::maybeEmit(
                    $code_base,
                    $declaration->getContext(),
                    Issue::AttributeNonClass,
                    $attribute->getLineno(),
                    $fqsen,
                    $class->isTrait() ? 'trait' : ($class->isInterface() ? 'interface' : 'abstract class')
                );
            }
        } else {
            Issue::maybeEmit(
                $code_base,
                $declaration->getContext(),
                Issue::UndeclaredClassAttribute,
                $attribute->getLineno(),
                $fqsen
            );
        }
    }

}
