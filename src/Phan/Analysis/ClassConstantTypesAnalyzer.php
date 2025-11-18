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
                            if ($code_base->hasClassWithFQSEN($type_fqsen->withAlternateId(1))) {
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

        // Check each constant defined in this class
        foreach ($clazz->getConstantMap($code_base) as $constant) {
            // Skip the magic ::class constant - it's automatically different in child classes
            if ($constant->getName() === 'class') {
                continue;
            }

            // Only check constants defined in this class
            if (!($constant->hasDefiningFQSEN() && $constant->getDefiningFQSEN() === $constant->getFQSEN())) {
                continue;
            }

            if (!$constant->hasDeclaredType()) {
                continue;
            }

            $constant_union_type = $constant->getUnionType();
            if (!$constant_union_type->hasRealTypeSet()) {
                continue;
            }

            $constant_real_type = $constant_union_type->getRealUnionType();

            // Check against each inherited class
            foreach ($inherited_class_list as $inherited_class) {
                $inherited_class->hydrate($code_base);

                if (!$inherited_class->hasConstantWithName($code_base, $constant->getName())) {
                    continue;
                }

                // Get the constant map directly to avoid exceptions for trait constants
                $inherited_constant_map = $inherited_class->getConstantMap($code_base);

                if (!isset($inherited_constant_map[$constant->getName()])) {
                    continue;
                }

                $inherited_constant = $inherited_constant_map[$constant->getName()];

                // Skip if it's the same constant (from traits)
                if ($inherited_constant->getDefiningFQSEN() === $constant->getDefiningFQSEN()) {
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

                // Private constants can change type freely
                if ($inherited_constant->isPrivate()) {
                    continue;
                }

                // Constants must be covariant: child type must be equal to parent type
                // PHP enforces invariance for typed constants (types must match exactly)
                if (!$constant_real_type->isEqualTo($inherited_real_type)) {
                    Issue::maybeEmit(
                        $code_base,
                        $constant->getContext(),
                        Issue::ConstantTypeMismatchInheritance,
                        $constant->getContext()->getLineNumberStart(),
                        $constant->getFQSEN(),
                        $constant_real_type,
                        $inherited_real_type,
                        $inherited_class->getFQSEN()
                    );
                }
            }
        }
    }

    private static function isAllowedClassConstantObjectType(CodeBase $code_base, Type $type): bool
    {
        if (Config::get_closest_minimum_target_php_version_id() < 80100) {
            return false;
        }
        if (!$type->isObject()) {
            return false;
        }
        $class_fqsen = FullyQualifiedClassName::fromType($type);
        if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
            return false;
        }
        return $code_base->getClassByFQSEN($class_fqsen)->isEnum();
    }
}
