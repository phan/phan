<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\Comment\Assertion;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\UnionType;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeFunctionCallCapability;

/**
 * Mark PHPUnit helper assertions as having side effects.
 *
 * - assertTrue
 * - assertNull
 * - assertNotNull
 * - assertFalse
 * - assertSame($expected, $actual)
 * - assertInstanceof
 *
 * NOTE: This will probably be rewritten
 */
class PHPUnitAssertionPlugin extends PluginV3 implements AnalyzeFunctionCallCapability
{
    /**
     * @override
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base): array
    {
        // @phan-suppress-next-line PhanThrowTypeAbsentForCall
        $assert_class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString('PHPUnit\Framework\Assert');
        if (!$code_base->hasClassWithFQSEN($assert_class_fqsen)) {
            if (!getenv('PHAN_PHPUNIT_ASSERTION_PLUGIN_QUIET')) {
                fwrite(STDERR, "PHPUnitAssertionPlugin failed to find class PHPUnit\Framework\Assert, giving up (set environment variable PHAN_PHPUNIT_ASSERTION_PLUGIN_QUIET=1 to ignore this)\n");
            }
            return [];
        }
        $result = [];
        foreach ($code_base->getMethodSet() as $method) {
            $method_fqsen = $method->getDefiningFQSEN();
            $class_fqsen = $method_fqsen->getFullyQualifiedClassName();
            if ($class_fqsen !== $assert_class_fqsen) {
                continue;
            }
            $closure = $this->createClosureForMethod($code_base, $method, $method_fqsen->getName());
            if (!$closure) {
                continue;
            }
            $result[(string)$method->getFQSEN()] = $closure;
        }
        return $result;
    }

    /**
     * @return ?Closure(CodeBase, Context, FunctionInterface, array, ?Node):void
     * @suppress PhanAccessClassConstantInternal, PhanAccessMethodInternal
     */
    private function createClosureForMethod(CodeBase $code_base, Method $method, string $name): ?Closure
    {
        // TODO: Add a helper method which will convert a doc comment and a stub php function source code to a closure for a param index (or indices)
        switch (\strtolower($name)) {
            case 'asserttrue':
            case 'assertnotfalse':
                return $method->createClosureForAssertion(
                    $code_base,
                    new Assertion(UnionType::empty(), 'unusedParamName', Assertion::IS_TRUE),
                    0
                );
            case 'assertfalse':
            case 'assertnottrue':
                return $method->createClosureForAssertion(
                    $code_base,
                    new Assertion(UnionType::empty(), 'unusedParamName', Assertion::IS_FALSE),
                    0
                );
                // TODO: Rest of https://github.com/sebastianbergmann/phpunit/issues/3368
            case 'assertisstring':
                // TODO: Could convert to real types?
                return $method->createClosureForAssertion(
                    $code_base,
                    new Assertion(UnionType::fromFullyQualifiedPHPDocString('string'), 'unusedParamName', Assertion::IS_OF_TYPE),
                    0
                );
            case 'assertnull':
                return $method->createClosureForAssertion(
                    $code_base,
                    new Assertion(UnionType::fromFullyQualifiedPHPDocString('null'), 'unusedParamName', Assertion::IS_OF_TYPE),
                    0
                );
            case 'assertnotnull':
                return $method->createClosureForAssertion(
                    $code_base,
                    new Assertion(UnionType::fromFullyQualifiedPHPDocString('null'), 'unusedParamName', Assertion::IS_NOT_OF_TYPE),
                    0
                );
            case 'assertsame':
                // Sets the type of $actual to $expected
                //
                // This is equivalent to the side effects of the below doc comment.
                // Note that the doc comment would make phan emit warnings about invalid classes, etc.
                // TODO: Reuse the code for templates here
                //
                // (at)template T
                // (at)param T $expected
                // (at)param mixed $actual
                // (at)phan-assert T $actual
                return $method->createClosureForUnionTypeExtractorAndAssertionType(
                    /**
                     * @param list<Node|string|int|float> $args
                     */
                    static function (CodeBase $code_base, Context $context, array $args): UnionType {
                        if (\count($args) < 2) {
                            return UnionType::empty();
                        }
                        return UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]);
                    },
                    Assertion::IS_OF_TYPE,
                    1
                );
            case 'assertinternaltype':
                return $method->createClosureForUnionTypeExtractorAndAssertionType(
                    /**
                     * @param list<Node|string|int|float> $args
                     */
                    function (CodeBase $code_base, Context $context, array $args): UnionType {
                        if (\count($args) < 2) {
                            return UnionType::empty();
                        }
                        $string = $args[0];
                        if ($string instanceof ast\Node) {
                            $string = (UnionTypeVisitor::unionTypeFromNode($code_base, $context, $string))->asSingleScalarValueOrNull();
                        }
                        if (!is_string($string)) {
                            return UnionType::empty();
                        }
                        $original_type = (UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[1]));
                        switch ($string) {
                            case 'numeric':
                                return UnionType::fromFullyQualifiedPHPDocString('int|float|string');
                            case 'integer':
                            case 'int':
                                return UnionType::fromFullyQualifiedPHPDocString('int');

                            case 'double':
                            case 'float':
                            case 'real':
                                return UnionType::fromFullyQualifiedPHPDocString('float');

                            case 'string':
                                return UnionType::fromFullyQualifiedPHPDocString('string');

                            case 'boolean':
                            case 'bool':
                                return UnionType::fromFullyQualifiedPHPDocString('bool');

                            case 'null':
                                return UnionType::fromFullyQualifiedPHPDocString('null');

                            case 'array':
                                $result = $original_type->arrayTypes();
                                if ($result->isEmpty()) {
                                    return UnionType::fromFullyQualifiedPHPDocString('array');
                                }
                                return $result;
                            case 'object':
                                $result = $original_type->objectTypes();
                                if ($result->isEmpty()) {
                                    return UnionType::fromFullyQualifiedPHPDocString('object');
                                }
                                return $result;
                            case 'resource':
                                return UnionType::fromFullyQualifiedPHPDocString('resource');
                            case 'scalar':
                                $result = $original_type->scalarTypes();
                                if ($result->isEmpty()) {
                                    return UnionType::fromFullyQualifiedPHPDocString('int|string|float|bool');
                                }
                                return $result;

                            case 'callable':
                                $result = $original_type->callableTypes();
                                if ($result->isEmpty()) {
                                    return UnionType::fromFullyQualifiedPHPDocString('callable');
                                }
                                return $result;
                        }
                        // Warn about possibly invalid assertion
                        // NOTE: This is only emitted for variables
                        $this->emitPluginIssue(
                            $code_base,
                            $context,
                            'PhanPluginPHPUnitAssertionInvalidInternalType',
                            'Unknown type {STRING_LITERAL} in call to assertInternalType',
                            [$string]
                        );

                        return UnionType::empty();
                    },
                    Assertion::IS_OF_TYPE,
                    1
                );
            case 'assertinstanceof':
                // This is equivalent to the side effects of the below doc comment.
                // Note that the doc comment would make phan emit warnings about invalid classes, etc.
                // TODO: Reuse the code for class-string<T> here.
                //
                // (at)template T
                // (at)param class-string<T> $expected
                // (at)param mixed $actual
                // (at)phan-assert T $actual
                return $method->createClosureForUnionTypeExtractorAndAssertionType(
                    /**
                     * @param list<Node|string|int|float> $args
                     */
                    static function (CodeBase $code_base, Context $context, array $args): UnionType {
                        if (\count($args) < 2) {
                            return UnionType::empty();
                        }
                        $string = (UnionTypeVisitor::unionTypeFromNode($code_base, $context, $args[0]))->asSingleScalarValueOrNull();
                        if (!is_string($string)) {
                            return UnionType::empty();
                        }
                        try {
                            return FullyQualifiedClassName::fromFullyQualifiedString($string)->asType()->asPHPDocUnionType();
                        } catch (\Exception $_) {
                            return UnionType::empty();
                        }
                    },
                    Assertion::IS_OF_TYPE,
                    1
                );
        }
        return null;
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new PHPUnitAssertionPlugin();
