<?php

declare(strict_types=1);

namespace Phan\Analysis;

use ast\Node;
use Phan\AST\ASTReverter;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\IssueFixSuggester;
use Phan\Language\Element\ClassConstant;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\EnumCase;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\NeverType;
use Phan\Language\Type\TemplateType;
use Phan\Language\UnionType;

/**
 * An analyzer that checks a class's properties for issues.
 */
class ClassConstantTypesAnalyzer
{

    /**
     * Check to see if the given class's properties have issues.
     */
    public static function analyzeClassConstantTypes(CodeBase $code_base, Clazz $clazz): void
    {
        foreach ($clazz->getConstantMap($code_base) as $constant) {
            // This phase is done before the analysis phase, so there aren't any dynamic properties to filter out.

            // Get the union type of this constant. This may throw (e.g. it can refers to missing elements).
            $comment = $constant->getComment();
            if (!$comment) {
                continue;
            }
            foreach ($comment->getVariableList() as $variable_comment) {
                try {
                    $union_type = $variable_comment->getUnionType();
                } catch (IssueException $exception) {
                    Issue::maybeEmitInstance(
                        $code_base,
                        $constant->getContext(),
                        $exception->getIssueInstance()
                    );
                    continue;
                }

                if ($union_type->hasTemplateTypeRecursive()) {
                    Issue::maybeEmit(
                        $code_base,
                        $constant->getContext(),
                        Issue::TemplateTypeConstant,
                        $constant->getFileRef()->getLineNumberStart(),
                        $constant->getFQSEN()
                    );
                }
                // Look at each type in the parameter's Union Type
                foreach ($union_type->withFlattenedArrayShapeOrLiteralTypeInstances()->getTypeSet() as $outer_type) {
                    $has_object = $outer_type->isObject() && !self::isAllowedClassConstantObjectType($code_base, $outer_type);
                    foreach ($outer_type->getReferencedClasses() as $type) {
                        if (!self::isAllowedClassConstantObjectType($code_base, $type)) {
                            $has_object = true;
                        }
                        // If it's a reference to self, its OK
                        if ($type->isSelfType()) {
                            continue;
                        }

                        if (!($constant->hasDefiningFQSEN() && $constant->getDefiningFQSEN() === $constant->getFQSEN())) {
                            continue;
                        }
                        if ($type instanceof TemplateType) {
                            continue;
                        }

                        // Make sure the class exists
                        $type_fqsen = FullyQualifiedClassName::fromType($type);

                        if ($code_base->hasClassWithFQSEN($type_fqsen)) {
                            if ($code_base->mayHaveAlternatesOfClass($type_fqsen) && $code_base->hasClassWithFQSEN($type_fqsen->withAlternateId(1))) {
                                UnionType::emitRedefinedClassReferenceWarning(
                                    $code_base,
                                    $constant->getContext(),
                                    $type_fqsen
                                );
                            }
                        } else {
                            Issue::maybeEmitWithParameters(
                                $code_base,
                                $constant->getContext(),
                                Issue::UndeclaredTypeClassConstant,
                                $constant->getFileRef()->getLineNumberStart(),
                                [$constant->getFQSEN(), (string)$outer_type],
                                IssueFixSuggester::suggestSimilarClass($code_base, $constant->getContext(), $type_fqsen, null, 'Did you mean', IssueFixSuggester::CLASS_SUGGEST_CLASSES_AND_TYPES)
                            );
                        }
                    }
                    if ($has_object) {
                        Issue::maybeEmitWithParameters(
                            $code_base,
                            $constant->getContext(),
                            Issue::CommentObjectInClassConstantType,
                            $constant->getFileRef()->getLineNumberStart(),
                            [$constant->getFQSEN(), (string)$outer_type]
                        );
                    }
                }
            }
        }

        // PHP 8.3+: Validate typed class constants
        self::validateTypedConstants($code_base, $clazz);

        // Check inheritance compatibility for typed constants
        self::checkConstantInheritance($code_base, $clazz);
    }

    /**
     * Validate that typed class constants (PHP 8.3+) have values matching their declared types
     */
    private static function validateTypedConstants(CodeBase $code_base, Clazz $clazz): void
    {
        foreach ($clazz->getConstantMap($code_base) as $constant) {
            // Only check constants defined in this class
            if (!($constant->hasDefiningFQSEN() && $constant->getDefiningFQSEN() === $constant->getFQSEN())) {
                continue;
            }
            if ($constant instanceof EnumCase) {
                continue;
            }

            try {
                $union_type = $constant->getUnionType();
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $code_base,
                    $constant->getContext(),
                    $exception->getIssueInstance()
                );
                continue;
            }

            if (!$union_type->hasRealTypeSet()) {
                continue;
            }

            if (!$constant->hasDeclaredType()) {
                continue;
            }

            $constant_context = $constant->getContext();
            $real_type = $union_type->getRealUnionType();

            // Check for 'never' type - always an error for constants
            if ($real_type->isType(NeverType::instance(false))) {
                Issue::maybeEmit(
                    $code_base,
                    $constant_context,
                    Issue::TypeMismatchDeclaredConstantNever,
                    $constant_context->getLineNumberStart(),
                    $constant->getFQSEN()
                );
                continue;
            }

            $value_node = $constant->getNodeForValue();
            if ($value_node === null) {
                continue;
            }

            // Get the inferred type of the constant value
            if ($value_node instanceof Node) {
                // For complex expressions, we need to evaluate them
                try {
                    $value_type = UnionTypeVisitor::unionTypeFromNode(
                        $code_base,
                        $constant_context,
                        $value_node
                    );
                } catch (IssueException) {
                    // If we can't determine the type, skip validation
                    continue;
                }
            } else {
                // For literals, get the type from the PHPDoc type set
                $value_type = $union_type->eraseRealTypeSet();
            }

            // Special case: float constants can accept integer values (per RFC)
            if ($real_type->hasType(FloatType::instance(false)) &&
                $value_type->isType(IntType::instance(false))) {
                continue;
            }

            // Check if the value type can cast to the declared type
            if (!$value_type->asExpandedTypes($code_base)->canCastToUnionType($real_type, $code_base)) {
                Issue::maybeEmit(
                    $code_base,
                    $constant_context,
                    Issue::TypeMismatchDeclaredConstant,
                    $constant_context->getLineNumberStart(),
                    $constant->getFQSEN(),
                    $real_type,
                    ASTReverter::toShortString($value_node),
                    $value_type
                );
            }
        }
    }

    /**
     * Check inheritance for constant type compatibility (covariance)
     */
    private static function checkConstantInheritance(CodeBase $code_base, Clazz $clazz): void
    {
        // Get the list of all inherited classes
        $inherited_class_list = $clazz->getAncestorClassList($code_base);

        if (!$inherited_class_list) {
            return;
        }

        $clazz->hydrate($code_base);

        // Fetch the constant map of each inherited class once, and the names of the inherited constants with a declared type:
        // only a constant with one of these names can be incompatible with an inherited constant.
        $inherited_constant_map_list = [];
        $typed_inherited_constant_names = [];
        foreach ($inherited_class_list as $i => $inherited_class) {
            $inherited_class->hydrate($code_base);
            // Get the constant map directly to avoid exceptions for trait constants
            $inherited_constant_map = $inherited_class->getConstantMap($code_base);
            $inherited_constant_map_list[$i] = $inherited_constant_map;
            foreach ($inherited_constant_map as $key => $inherited_constant) {
                if ($inherited_constant->hasDeclaredType()) {
                    $typed_inherited_constant_names[$key] = true;
                }
            }
        }
        if (!$typed_inherited_constant_names) {
            return;
        }
        $uses_traits = (bool)$clazz->getTraitFQSENList();
        // An untyped constant cannot override a typed constant in PHP 8.3+
        $check_untyped_constants = Config::get_closest_target_php_version_id() >= 80300;
        $class_context = null;

        // Check each constant defined in this class (or imported directly from a trait)
        foreach ($clazz->getConstantMap($code_base) as $constant) {
            $name = $constant->getName();
            if (!isset($typed_inherited_constant_names[$name])) {
                continue;
            }
            // Skip the magic ::class constant - it's automatically different in child classes
            if ($name === 'class') {
                continue;
            }

            if (!$constant->hasDefiningFQSEN()) {
                continue;
            }
            if ($constant->getDefiningFQSEN() !== $constant->getFQSEN()) {
                if (!$uses_traits) {
                    // Inherited from a parent class or an interface
                    continue;
                }
                // Public constants imported from a trait keep the trait's defining FQSEN.
                $defining_class_fqsen = $constant->getDefiningFQSEN()->getFullyQualifiedClassName();
                if (!$code_base->hasClassWithFQSEN($defining_class_fqsen) || !$code_base->getClassByFQSEN($defining_class_fqsen)->isTrait()) {
                    continue;
                }
            }
            // True for the copy of a constant imported from a trait, including the copy of a protected or private constant
            // (the defining FQSEN of such a copy is this class)
            $is_from_trait = $constant->getRealDefiningFQSEN() !== $constant->getFQSEN();

            $constant_has_declared_type = $constant->hasDeclaredType();
            $constant_real_type = null;
            $constant_has_self_type = false;
            if ($constant_has_declared_type) {
                $constant_union_type = $constant->getUnionType();
                if (!$constant_union_type->hasRealTypeSet()) {
                    continue;
                }
                $constant_raw_real_type = $constant_union_type->getRealUnionType();
                $constant_has_self_type = $constant_raw_real_type->hasSelfType();
                if ($clazz->isTrait()) {
                    $constant_real_type = $constant_raw_real_type;
                } else {
                    $class_context ??= $clazz->getContext()->withScope($clazz->getInternalScope());
                    $constant_real_type = $constant_raw_real_type->withSelfResolvedInContext($class_context);
                }
            } elseif (!$check_untyped_constants) {
                continue;
            }

            // Check against each inherited class
            foreach ($inherited_class_list as $i => $inherited_class) {
                $inherited_constant = $inherited_constant_map_list[$i][$name] ?? null;
                if (!$inherited_constant) {
                    continue;
                }

                // Skip if it's the same constant (from traits)
                if ($inherited_constant->getRealDefiningFQSEN() === $constant->getRealDefiningFQSEN()) {
                    continue;
                }

                if (!$inherited_constant->hasDeclaredType()) {
                    continue;
                }

                $inherited_union_type = $inherited_constant->getUnionType();
                if (!$inherited_union_type->hasRealTypeSet()) {
                    // Parent has no declared type - child can add a type
                    continue;
                }

                $inherited_real_type = $inherited_union_type->getRealUnionType();
                // The inherited constants of a trait are those of the traits it uses. Their types are not compared
                // when one of them contains `self` (see Clazz::areConstantsCompatible()).
                if ($clazz->isTrait() && ($constant_has_self_type || $inherited_real_type->hasSelfType())) {
                    continue;
                }
                // Only the type of a constant of a trait keeps `self` unresolved (it is resolved when a class or interface is parsed).
                if ($inherited_real_type->hasSelfType()) {
                    $self_class = self::getClassBindingSelfOfConstant($code_base, $inherited_class, $inherited_constant);
                    $inherited_real_type = $inherited_real_type->withSelfResolvedInContext(
                        $self_class->getContext()->withScope($self_class->getInternalScope())
                    );
                }

                // Private constants can change type freely
                if ($inherited_constant->isPrivate()) {
                    continue;
                }

                if ($constant_real_type === null) {
                    // The type of an internal constant comes from the PHP version running Phan or from bundled stubs
                    // (e.g. spl.phan_php), both of which may reflect PHP 8.4+ where most internal class constants gained types
                    // (few had one in PHP 8.3). PHP 8.5 and 8.6 only added new constants with a type. So for a target of 8.4
                    // or newer, an internal constant with a type also has it in the target version, if it exists there.
                    // For a target of 8.3 that is not known.
                    if ($inherited_constant->isPHPInternal()
                        && Config::get_closest_target_php_version_id() < 80400
                    ) {
                        continue;
                    }
                } elseif ($constant_real_type->isStrictSubtypeOf($code_base, $inherited_real_type)
                    || (!$clazz->isTrait() && $constant_real_type->hasStaticType()
                        && $constant_real_type->withStaticResolvedInContext($class_context ??= $clazz->getContext()->withScope($clazz->getInternalScope()))->isStrictSubtypeOf($code_base, $inherited_real_type))
                    || self::isCovariantWithCanonicalTypes($code_base, $constant_real_type, $inherited_real_type)
                ) {
                    // PHP enforces covariance for typed constants: every type in the child must be a subtype of (or equal to) the parent
                    continue;
                }

                // Interfaces are never included in the trait/parent composition conflict check.
                // Their constant types must still be checked for covariance in PHP 8.3+.
                if ($is_from_trait && !$inherited_class->isInterface()
                    // @phan-suppress-next-line PhanAccessMethodInternal
                    && $clazz->isTraitConstantConflictingWith($code_base, $constant, $inherited_constant, $inherited_class->isTrait())
                ) {
                    // PhanIncompatibleCompositionConstant is reported for these constants
                    continue;
                }

                // Report a constant imported from a trait at the class using the trait, in the scope of that class
                // (so that a suppression on the class applies).
                $issue_context = $is_from_trait
                    ? ($class_context ??= $clazz->getContext()->withScope($clazz->getInternalScope()))
                    : $constant->getContext();
                Issue::maybeEmit(
                    $code_base,
                    $issue_context,
                    Issue::ConstantTypeMismatchInheritance,
                    $issue_context->getLineNumberStart(),
                    $constant->getFQSEN(),
                    $constant_real_type ?? '(none)',
                    $inherited_real_type,
                    $inherited_class->getFQSEN()
                );
            }
        }
    }

    /**
     * Returns true if $type is covariant with $inherited_type after canonicalizing both as the composition check does
     * (see Clazz::canonicalizeDeclaredConstantType()). PHP treats `iterable` as `array|\Traversable`, so `?iterable` is covariant
     * with `array|\Traversable|null`. The caller checks this only after the check of the types as declared failed.
     */
    private static function isCovariantWithCanonicalTypes(CodeBase $code_base, UnionType $type, UnionType $inherited_type): bool
    {
        // @phan-suppress-next-line PhanAccessMethodInternal
        $canonical_type = Clazz::canonicalizeDeclaredConstantType($code_base, $type);
        // @phan-suppress-next-line PhanAccessMethodInternal
        return $canonical_type->isStrictSubtypeOf($code_base, Clazz::canonicalizeDeclaredConstantType($code_base, $inherited_type));
    }

    /**
     * Returns the class that `self` refers to in the type of $constant, a constant of $class that comes from a trait.
     * PHP also evaluates the value of such a constant in the scope of that class when it fetches the constant.
     *
     * PHP binds `self` of a constant of a trait to the class using the trait, and subclasses inherit the constant
     * with that binding. This is the nearest class from $class up the parent chain that imports $constant from
     * one of its traits ($class itself if there is none).
     *
     * @internal used by Clazz
     */
    public static function getClassBindingSelfOfConstant(CodeBase $code_base, Clazz $class, ClassConstant $constant): Clazz
    {
        $name = $constant->getName();
        $real_defining_fqsen = $constant->getRealDefiningFQSEN();
        $seen = [];
        // $seen guards against inheritance cycles
        while (!isset($seen[\spl_object_id($class)])) {
            $seen[\spl_object_id($class)] = true;
            foreach ($class->getTraitFQSENList() as $trait_fqsen) {
                if (!$code_base->hasClassWithFQSEN($trait_fqsen)) {
                    continue;
                }
                $trait_constant = $code_base->getClassByFQSEN($trait_fqsen)->getConstantMap($code_base)[$name] ?? null;
                if ($trait_constant && $trait_constant->getRealDefiningFQSEN() === $real_defining_fqsen) {
                    return $class;
                }
            }
            if (!$class->hasParentType()) {
                break;
            }
            $parent_fqsen = $class->getParentClassFQSEN();
            if (!$code_base->hasClassWithFQSEN($parent_fqsen)) {
                break;
            }
            $parent = $code_base->getClassByFQSEN($parent_fqsen);
            $parent_constant = $parent->getConstantMap($code_base)[$name] ?? null;
            if (!$parent_constant || $parent_constant->getRealDefiningFQSEN() !== $real_defining_fqsen) {
                break;
            }
            // $class inherits $constant from its parent
            $class = $parent;
        }
        return $class;
    }

    private static function isAllowedClassConstantObjectType(CodeBase $code_base, Type $type): bool
    {
        if (Config::get_closest_minimum_target_php_version_id() < 80100) {
            return false;
        }
        if (!$type->isObjectWithKnownFQSEN()) {
            // e.g. an intersection type such as `self&B`
            return false;
        }
        $class_fqsen = FullyQualifiedClassName::fromType($type);
        if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
            return false;
        }
        return $code_base->getClassByFQSEN($class_fqsen)->isEnum();
    }
}
