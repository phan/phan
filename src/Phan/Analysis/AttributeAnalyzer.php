<?php

declare(strict_types=1);

namespace Phan\Analysis;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Element\AddressableElementInterface;
use Phan\Language\Element\Attribute;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Parameter;
use Phan\Issue;

/**
 * Analyzer of the attributes of declarations.
 * Emits warnings, and will eventually modify the way the element is analyzed.
 * (this is why it's run before starting the analysis phase)
 *
 * NOTE: This runs without problems in php 7 because it uses constants from \Phan\Language\Element\Attribute, not from \Attribute
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
        $attribute_list = $method->getAttributeList();
        if ($attribute_list) {
            self::checkAttributeList($code_base, $method, $method, $attribute_list);
        }
        foreach ($method->getRealParameterList() as $parameter) {
            $attribute_list = $parameter->getAttributeList();
            if ($attribute_list) {
                self::checkAttributeList($code_base, $method, $parameter, $attribute_list);
            }
        }
    }

    /**
     * Analyze attributes of the provided class and the attributes of the class declaration
     */
    public static function analyzeAttributesOfClass(CodeBase $code_base, Clazz $class): void
    {
        self::analyzeAttributesOfElement($code_base, $class);
        foreach ($class->getPropertyMap($code_base) as $property) {
            if ($property->getFQSEN() === $property->getRealDefiningFQSEN()) {
                self::analyzeAttributesOfElement($code_base, $property);
            }
        }
        foreach ($class->getConstantMap($code_base) as $const) {
            if ($const->getFQSEN() === $const->getRealDefiningFQSEN()) {
                self::analyzeAttributesOfElement($code_base, $const);
            }
        }
        foreach ($class->getMethodMap($code_base) as $method) {
            if ($method->getFQSEN() === $method->getRealDefiningFQSEN()) {
                self::analyzeAttributesOfElement($code_base, $method);
            }
        }
    }

    /**
     * Check attributes of non-functionlikes
     *
     * This will also warn if method parameters are incompatible with the parameters of ancestor methods.
     */
    private static function analyzeAttributesOfElement(
        CodeBase $code_base,
        AddressableElementInterface $element
    ): void {
        $attribute_list = $element->getAttributeList();
        if ($attribute_list) {
            self::checkAttributeList($code_base, $element, $element, $attribute_list);
        }
    }

    /**
     * @param AddressableElementInterface|Parameter $element @phan-unused-param
     * @param non-empty-list<Attribute> $attribute_list
     */
    private static function checkAttributeList(
        CodeBase $code_base,
        AddressableElementInterface $declaration,
        object $element,
        array $attribute_list
    ): void {
        $attribute_set = [];
        foreach ($attribute_list as $attribute) {
            self::checkAttribute($code_base, $declaration, $element, $attribute);

            $fqsen = $attribute->getFQSEN();
            $fqsen_id = \spl_object_id($fqsen);
            $previous_attribute = $attribute_set[$fqsen_id] ?? null;
            if ($previous_attribute instanceof Attribute) {
                // This is a repeated attribute
                if (!$code_base->hasClassWithFQSEN($fqsen)) {
                    continue;
                }
                $class = $code_base->getClassByFQSEN($fqsen);
                if ($class->getAttributeFlags($code_base) & Attribute::IS_REPEATABLE) {
                    continue;
                }
                Issue::maybeEmit(
                    $code_base,
                    $declaration->getContext(),
                    Issue::AttributeNonRepeatable,
                    $attribute->getLineno(),
                    $fqsen,
                    $class->getContext()->getFile(),
                    $class->getContext()->getLineNumberStart(),
                    $previous_attribute->getLineno()
                );
            } else {
                $attribute_set[$fqsen_id] = $attribute;
            }
        }
    }

    /**
     * @param AddressableElementInterface|Parameter $element @phan-unused-param
     */
    private static function checkAttribute(
        CodeBase $code_base,
        AddressableElementInterface $declaration,
        object $element,
        Attribute $attribute
    ): void {
        $fqsen = $attribute->getFQSEN();
        if ($code_base->hasClassWithFQSEN($fqsen)) {
            $class = $code_base->getClassByFQSEN($fqsen);
            if ($class->isClass() && !$class->isAbstract()) {
                if (!$class->isAttribute()) {
                    Issue::maybeEmit(
                        $code_base,
                        $declaration->getContext(),
                        Issue::AttributeNonAttribute,
                        $attribute->getLineno(),
                        $fqsen,
                        '#[\Attribute(...)]'
                    );
                }
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
        if ($attribute->getLineno() === $element->getFileRef()->getLineNumberStart()) {
            if (Config::get_closest_minimum_target_php_version_id() < 80000) {
                Issue::maybeEmit(
                    $code_base,
                    $declaration->getContext(),
                    Issue::CompatibleAttributeOnSameLine,
                    $attribute->getLineno(),
                    $attribute,
                    $element
                );
            }
        }
    }

}
