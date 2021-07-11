<?php

declare(strict_types=1);

namespace Phan\Analysis;

use ast;
use ast\Node;
use Phan\AST\ASTReverter;
use Phan\CodeBase;
use Phan\Config;
use Phan\Issue;
use Phan\Language\Element\AddressableElementInterface;
use Phan\Language\Element\Attribute;
use Phan\Language\Element\ClassConstant;
use Phan\Language\Element\ClassElement;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\Element\Parameter;
use Phan\Language\Element\Property;
use Phan\Parse\ParseVisitor;

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
                    $attribute->getLineNumberStart(),
                    $fqsen,
                    $class->getContext()->getFile(),
                    $class->getContext()->getLineNumberStart(),
                    $previous_attribute->getLineNumberStart()
                );
            } else {
                $attribute_set[$fqsen_id] = $attribute;
            }
        }
    }

    /**
     * @param AddressableElementInterface|Parameter $element @phan-unused-param
     */
    private static function getTargetConstantForElement(object $element): int
    {
        if ($element instanceof ClassElement) {
            if ($element instanceof Property) {
                return Attribute::TARGET_PROPERTY;
            } elseif ($element instanceof ClassConstant) {
                return Attribute::TARGET_CLASS_CONSTANT;
            } elseif ($element instanceof Method) {
                return Attribute::TARGET_METHOD;
            }
        } elseif ($element instanceof Clazz) {
            return Attribute::TARGET_CLASS;
        } elseif ($element instanceof Func) {
            return Attribute::TARGET_FUNCTION;
        } elseif ($element instanceof Parameter) {
            return Attribute::TARGET_PARAMETER;
        }
        return 0;
    }

    private const ATTRIBUTE_TARGET_NAME = [
        0                                => 'unknown',
        Attribute::TARGET_CLASS          => '\Attribute::TARGET_CLASS',
        Attribute::TARGET_CLASS_CONSTANT => '\Attribute::TARGET_CLASS_CONSTANT',
        Attribute::TARGET_PARAMETER      => '\Attribute::TARGET_PARAMETER',
        Attribute::TARGET_PROPERTY       => '\Attribute::TARGET_PROPERTY',
        Attribute::TARGET_METHOD         => '\Attribute::TARGET_METHOD',
        Attribute::TARGET_FUNCTION       => '\Attribute::TARGET_FUNCTION',
    ];

    /**
     * Get a representation of the list of attribute target class constant names for a bitfield
     */
    private static function getTargetNames(int $expected_targets): string
    {
        $parts = [];
        foreach (self::ATTRIBUTE_TARGET_NAME as $value => $name) {
            if ($value & $expected_targets) {
                $parts[] = $name;
            }
        }
        return $parts ? \implode('|', $parts) : '(no valid \Attribute::TARGET_* values)';
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
        $attribute_lineno = $attribute->getLineNumberStart();
        $fqsen = $attribute->getFQSEN();
        if ($code_base->hasClassWithFQSEN($fqsen)) {
            $class = $code_base->getClassByFQSEN($fqsen);
            if ($class->isClass() && !$class->isAbstract()) {
                if (!$class->isAttribute()) {
                    Issue::maybeEmit(
                        $code_base,
                        $declaration->getContext(),
                        Issue::AttributeNonAttribute,
                        $attribute_lineno,
                        $fqsen,
                        '#[\Attribute(...)]'
                    );
                }
                $expected_flags = $class->getAttributeFlags($code_base);
                $actual_flag = self::getTargetConstantForElement($element);
                if (!($actual_flag & $expected_flags)) {
                    Issue::maybeEmit(
                        $code_base,
                        $declaration->getContext(),
                        Issue::AttributeWrongTarget,
                        $attribute_lineno,
                        $fqsen,
                        $class->getContext()->getFile(),
                        $class->getContext()->getLineNumberStart(),
                        self::getTargetNames($expected_flags),
                        $element,
                        self::getTargetNames($actual_flag)
                    );
                }
                // TODO: Pass this to the method call analyzer?
                $class->addReference($declaration->getContext());
                if ($class->isDeprecated()) {
                    Issue::maybeEmit(
                        $code_base,
                        $declaration->getContext(),
                        Issue::DeprecatedClass,
                        $attribute_lineno,
                        (string)$class->getFQSEN(),
                        $class->getContext()->getFile(),
                        $class->getContext()->getLineNumberStart(),
                        $class->getDeprecationReason()
                    );
                }
                $constructor = $class->getMethodByName($code_base, '__construct');
                if (!$constructor->isPublic()) {
                    Issue::maybeEmit(
                        $code_base,
                        $declaration->getContext(),
                        Issue::AccessNonPublicAttribute,
                        $attribute_lineno,
                        (string)$class->getFQSEN(),
                        $constructor->getRepresentationForIssue(),
                        $class->getContext()->getFile(),
                        $class->getContext()->getLineNumberStart()
                    );
                }
                $attribute_node = $attribute->getNode();

                if ($attribute_node) {
                    foreach ($attribute_node->children['args']->children ?? [] as $arg_node) {
                        if (!$arg_node instanceof Node) {
                            continue;
                        }
                        if ($arg_node->kind === ast\AST_NAMED_ARG) {
                            $arg_node = $arg_node->children['expr'];
                        }

                        if ($arg_node instanceof Node) {
                            (new ParseVisitor($code_base, $declaration->getContext()))->checkNodeIsConstExprOrWarn($arg_node, ParseVisitor::CONSTANT_EXPRESSION_IN_ATTRIBUTE);
                        }
                    }
                    ArgumentType::analyze($constructor, $attribute_node, $declaration->getContext(), $code_base);
                }
            } else {
                Issue::maybeEmit(
                    $code_base,
                    $declaration->getContext(),
                    Issue::AttributeNonClass,
                    $attribute_lineno,
                    $fqsen,
                    $class->isTrait() ? 'trait' : ($class->isInterface() ? 'interface' : 'abstract class')
                );
            }
        } else {
            Issue::maybeEmit(
                $code_base,
                $declaration->getContext(),
                Issue::UndeclaredClassAttribute,
                $attribute_lineno,
                $fqsen
            );
        }
        if (Config::get_closest_minimum_target_php_version_id() < 80000) {
            $attribute_group_start_lineno = $attribute->getGroupLineNumberStart();
            $attribute_group_end_lineno = $attribute->getGroupLineNumberEnd();
            if ($attribute_group_start_lineno === $element->getFileRef()->getLineNumberStart()) {
                Issue::maybeEmit(
                    $code_base,
                    $declaration->getContext(),
                    Issue::CompatibleAttributeGroupOnSameLine,
                    $attribute_group_end_lineno,
                    ASTReverter::toShortString($attribute->getGroup()),
                    $element
                );
            }
            if ($attribute_group_end_lineno > $attribute_group_start_lineno) {
                Issue::maybeEmit(
                    $code_base,
                    $declaration->getContext(),
                    Issue::CompatibleAttributeGroupOnMultipleLines,
                    $attribute_group_start_lineno,
                    ASTReverter::toShortString($attribute->getGroup()),
                    $element,
                    $attribute_group_end_lineno
                );
            }
        }
    }
}
