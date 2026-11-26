<?php

declare(strict_types=1);

namespace Phan\Analysis\ConditionVisitor;

use ast\Node;
use Phan\Analysis\ConditionVisitor;
use Phan\Analysis\ConditionVisitorInterface;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NullType;
use Phan\Language\UnionType;

/**
 * An expression with the side effect that the given node has type T, where the types that the node already has
 * are kept if they are subtypes of T: an `array{id:int}` that is asserted to be an `array` stays an `array{id:int}`.
 *
 * (HasTypeCondition replaces the type with T)
 */
class NarrowsToTypeCondition extends HasTypeCondition
{
    /** @var UnionType the type that this is asserting an argument has */
    private $asserted_type;

    public function __construct(UnionType $type)
    {
        parent::__construct($type);
        $this->asserted_type = $type;
    }

    /**
     * @param Node $var
     * @param Node|int|string|float $expr @unused-param
     * @override
     */
    public function analyzeVar(ConditionVisitorInterface $visitor, Node $var, $expr): Context
    {
        $context = $visitor->getContext();
        try {
            $variable = $visitor->getVariableFromScope($var, $context);
        } catch (\Exception) {
            return $context;
        }
        if (\is_null($variable)) {
            return $context;
        }

        $variable = clone($variable);
        $variable->setUnionType(self::narrow($visitor->getCodeBase(), $context, $variable->getUnionType(), $this->asserted_type));

        return $context->withScopeVariable($variable);
    }

    /**
     * @return UnionType $asserted_type, or the types of $old_type that are subtypes of it if nothing else in $old_type can be of that type.
     */
    public static function narrow(CodeBase $code_base, Context $context, UnionType $old_type, UnionType $asserted_type): UnionType
    {
        if ($old_type->isEmpty()) {
            return $asserted_type;
        }
        $asserted_type_set = $asserted_type->getTypeSet();
        $is_class_assertion = true;
        foreach ($asserted_type_set as $type) {
            // (`Box<int>` says more than `instanceof Box`: not narrowed as a class)
            if ($type->isNullable() || !$type->hasObjectWithKnownFQSEN() || $type->hasTemplateParameterTypes()) {
                $is_class_assertion = false;
                break;
            }
        }
        if ($is_class_assertion) {
            // As for `$x instanceof C`
            return ConditionVisitor::calculateNarrowedUnionType($code_base, $context, $old_type, $asserted_type);
        }
        $allows_null = $asserted_type->containsNullable();
        $new_type_set = [];
        foreach ($old_type->getTypeSet() as $type) {
            if ($type instanceof NullType) {
                if ($allows_null) {
                    $new_type_set[] = $type;
                }
                continue;
            }
            if ($type->isNullable()) {
                if ($allows_null) {
                    // null is possible for both types, whatever happens to the rest of this type
                    $new_type_set[] = NullType::instance(false);
                }
                $type = $type->withIsNullable(false);
            }
            if (self::isSubtypeOfAny($type, $asserted_type_set, $code_base)) {
                $new_type_set[] = $type;
                continue;
            }
            if ($type instanceof MixedType) {
                return $asserted_type;
            }
            $is_class = $type->hasObjectWithKnownFQSEN();
            foreach ($asserted_type_set as $asserted) {
                $asserted = $asserted->withIsNullable(false);
                // A value of this type may be of the asserted type, and nothing more is known about it:
                // the asserted type is a subtype, or both are classes or interfaces (an object can be an instance of both).
                if ($asserted->isSubtypeOf($type, $code_base) || ($is_class && $asserted->hasObjectWithKnownFQSEN())) {
                    $new_type_set[] = $asserted;
                }
            }
        }
        if (!$new_type_set) {
            return $asserted_type;
        }
        return UnionType::of($new_type_set, $old_type->getRealTypeSet());
    }

    /**
     * @param list<Type> $type_set
     */
    private static function isSubtypeOfAny(Type $type, array $type_set, CodeBase $code_base): bool
    {
        foreach ($type_set as $other_type) {
            if ($type->isSubtypeOf($other_type, $code_base)) {
                return true;
            }
        }
        return false;
    }
}
