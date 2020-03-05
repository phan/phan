<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\FQSEN;
use Phan\Language\UnionType;
use Phan\Library\Map;
use Phan\Library\Set;
use Phan\PluginV3;
use Phan\PluginV3\FinalizeProcessCapability;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This plugin checks for return types that can be made more specific.
 *
 * - E.g. `/** (at)return object (*)/ function () { return new ArrayObject(); }`
 *   could be documented as returning an ArrayObject instead.
 *
 * This file demonstrates plugins for Phan. Plugins hook into various events.
 * MoreSpecificElementTypePlugin hooks into two events:
 *
 * - getPostAnalyzeNodeVisitorClassName
 *   This method returns a visitor that is called on every AST node from every
 *   file being analyzed in post-order
 * - finalizeProcess
 *   This is called after the other forms of analysis are finished running.
 *
 * A plugin file must
 *
 * - Contain a class that inherits from \Phan\PluginV3
 *
 * - End by returning an instance of that class.
 *
 * It is assumed without being checked that plugins aren't
 * mangling state within the passed code base or context.
 *
 * Note: When adding new plugins,
 * add them to the corresponding section of README.md
 *
 * TODO: Account for methods in traits being possibly overrides
 */
class MoreSpecificElementTypePlugin extends PluginV3 implements
    PostAnalyzeNodeCapability,
    FinalizeProcessCapability
{
    /** @var Map<FQSEN,ElementTypeInfo> maps function/method/closure FQSEN to function info and the set of union types they return */
    public static $method_return_types;

    /** @var Set<FQSEN> the set of function/method/closure FQSENs that don't need to be more specific. */
    public static $method_blacklist;

    /**
     * @return class-string - name of PluginAwarePostAnalysisVisitor subclass
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return MoreSpecificElementTypeVisitor::class;
    }

    /**
     * Record that $function contains a return statement which returns an expression of type $return_type.
     *
     * This may be called multiple times for the same return statement (Phan recursively analyzes functions with underspecified param types by default)
     */
    public static function recordType(FunctionInterface $function, UnionType $return_type): void
    {
        $fqsen = $function->getFQSEN();
        if (self::$method_blacklist->offsetExists($fqsen)) {
            return;
        }
        if ($return_type->isEmpty()) {
            self::$method_blacklist->attach($fqsen);
            self::$method_return_types->offsetUnset($fqsen);
            return;
        }
        if (self::$method_return_types->offsetExists($fqsen)) {
            self::$method_return_types->offsetGet($fqsen)->types->attach($return_type);
        } else {
            self::$method_return_types->offsetSet($fqsen, new ElementTypeInfo($function, [$return_type]));
        }
    }

    private static function shouldWarnAboutMoreSpecificType(CodeBase $code_base, UnionType $actual_type, UnionType $declared_return_type): bool
    {
        if ($declared_return_type->isEmpty()) {
            // There was no phpdoc type declaration, so let UnknownElementTypePlugin warn about that instead of this.
            // This plugin warns about `@return mixed` but not the absence of a declaration because the former normally prevents phan from inferring something more specific.
            return false;
        }
        if ($declared_return_type->containsNullable() && !$actual_type->containsNullable()) {
            // Warn about `Subclass1|Subclass2` being the real return type of `?BaseClass`
            // because the actual returned type is non-null
            return true;
        }
        if ($declared_return_type->typeCount() === 1) {
            if ($declared_return_type->getTypeSet()[0]->isObjectWithKnownFQSEN()) {
                if ($actual_type->typeCount() >= 2) {
                    // Don't warn about Subclass1|Subclass2 being more specific than BaseClass
                    return false;
                }
            }
        }
        if ($declared_return_type->isStrictSubtypeOf($code_base, $actual_type)) {
            return false;
        }
        if (!$actual_type->asExpandedTypes($code_base)->canCastToUnionType($declared_return_type)) {
            // Don't warn here about type mismatches such as int->string or object->array, but do warn about SubClass->BaseClass.
            // Phan should warn elsewhere about those mismatches
            return false;
        }
        if ($declared_return_type->hasTopLevelArrayShapeTypeInstances()) {
            return false;
        }
        $real_actual_type = $actual_type->getRealUnionType();
        if (!$real_actual_type->isEmpty() && $declared_return_type->isStrictSubtypeOf($code_base, $real_actual_type)) {
            // TODO: Provide a way to disable this heuristic.
            return false;
        }
        return true;
    }

    private static function containsObjectWithKnownFQSEN(UnionType $union_type): bool
    {
        foreach ($union_type->getTypesRecursively() as $type) {
            if ($type->isObjectWithKnownFQSEN()) {
                return true;
            }
        }
        return false;
    }

    /**
     * After all return statements are gathered, suggest a more specific type for the various functions.
     */
    public function finalizeProcess(CodeBase $code_base): void
    {
        foreach (self::$method_return_types as $type_info) {
            $function = $type_info->function;
            $function_context = $function->getContext();
            // TODO: Do a better job for Traversable<MyClass> and iterable<MyClass>
            $actual_type = UnionType::merge($type_info->types->toArray())->withStaticResolvedInContext($function_context)->eraseTemplatesRecursive()->asNormalizedTypes();
            $declared_return_type = $function->getOriginalReturnType()->withStaticResolvedInContext($function_context)->eraseTemplatesRecursive()->asNormalizedTypes();
            if (!self::shouldWarnAboutMoreSpecificType($code_base, $actual_type, $declared_return_type)) {
                continue;
            }
            if (self::containsObjectWithKnownFQSEN($actual_type) && !self::containsObjectWithKnownFQSEN($declared_return_type)) {
                $issue_type = 'PhanPluginMoreSpecificActualReturnTypeContainsFQSEN';
                $issue_message = 'Phan inferred that {FUNCTION} documented to have return type {TYPE} (without an FQSEN) returns the more specific type {TYPE} (with an FQSEN)';
            } else {
                $issue_type = 'PhanPluginMoreSpecificActualReturnType';
                $issue_message = 'Phan inferred that {FUNCTION} documented to have return type {TYPE} returns the more specific type {TYPE}';
            }

            $this->emitIssue(
                $code_base,
                $function->getContext(),
                $issue_type,
                $issue_message,
                [
                    $function->getRepresentationForIssue(),
                    $declared_return_type,
                    $actual_type->getDebugRepresentation()
                ]
            );
        }
    }
}

/**
 * Represents the actual return types seen during analysis
 * (including recursive analysis)
 */
class ElementTypeInfo
{
    /** @var FunctionInterface the function with the return values*/
    public $function;
    /** @var Set<UnionType> the set of observed return types */
    public $types;
    /**
     * @param list<UnionType> $return_types
     */
    public function __construct(FunctionInterface $function, array $return_types)
    {
        $this->function = $function;
        $this->types = new Set($return_types);
    }
}
MoreSpecificElementTypePlugin::$method_blacklist = new Set();
MoreSpecificElementTypePlugin::$method_return_types = new Map();

/**
 * This visitor analyzes node kinds that can be the root of expressions
 * containing duplicate expressions, and is called on nodes in post-order.
 */
class MoreSpecificElementTypeVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * @param Node $node a node of kind ast\AST_RETURN, representing a return statement.
     */
    public function visitReturn(Node $node): void
    {
        if (!$this->context->isInFunctionLikeScope()) {
            return;
        }
        try {
            $function = $this->context->getFunctionLikeInScope($this->code_base);
        } catch (Exception $_) {
            return;
        }
        if ($function->hasYield()) {
            // TODO: Support analyzing yield key/value types of generators?
            return;
        }
        if ($function instanceof Method) {
            // Skip functions that are overrides or are overridden.
            // They may be documenting a less specific return type to deal with the inheritance hierarchy.
            if ($function->isOverride() || $function->isOverriddenByAnother()) {
                return;
            }
        }
        try {
            // Fetch the list of valid classes, and warn about any undefined classes.
            // (We have more specific issue types such as PhanNonClassMethodCall below, don't emit PhanTypeExpected*)
            $union_type = UnionTypeVisitor::unionTypeFromNode($this->code_base, $this->context, $node->children['expr']);
        } catch (Exception $_) {
            // Phan should already throw for this
            return;
        }
        MoreSpecificElementTypePlugin::recordType($function, $union_type->withFlattenedArrayShapeOrLiteralTypeInstances());
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new MoreSpecificElementTypePlugin();
