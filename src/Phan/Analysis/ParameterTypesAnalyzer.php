<?php

declare(strict_types=1);

namespace Phan\Analysis;

use ast;
use ast\Node;
use Phan\AST\ASTReverter;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\CodeBaseException;
use Phan\Exception\RecursionDepthException;
use Phan\Issue;
use Phan\IssueFixSuggester;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Comment\Parameter as CommentParameter;
use Phan\Language\Element\Flags;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\Element\Parameter;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type\IterableType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\ObjectType;
use Phan\Language\Type\ScalarType;
use Phan\Language\Type\StaticOrSelfType;
use Phan\Language\Type\TemplateType;
use Phan\Language\Type\VoidType;
use Phan\Language\UnionType;

use function array_merge;
use function strcasecmp;

/**
 * Analyzer of the parameters of function/closure/method signatures.
 *
 * This will modify union types of parameter declarations based on available information
 * such as inheritance, parameter defaults, etc.
 *
 * This will also warn if inherited parameters are invalid.
 *
 * (Depends on configuration settings)
 */
class ParameterTypesAnalyzer
{

    /**
     * Check function, closure, and method parameters to make sure they're valid
     *
     * This will also warn if method parameters are incompatible with the parameters of ancestor methods.
     */
    public static function analyzeParameterTypes(
        CodeBase $code_base,
        FunctionInterface $method
    ): void {
        try {
            self::analyzeParameterTypesInner($code_base, $method);
        } catch (RecursionDepthException $_) {
        }
    }

    /**
     * @see analyzeParameterTypes
     */
    private static function analyzeParameterTypesInner(
        CodeBase $code_base,
        FunctionInterface $method
    ): void {
        if (Config::getValue('check_docblock_signature_param_type_match')) {
            self::analyzeParameterTypesDocblockSignaturesMatch($code_base, $method);
        }

        self::checkCommentParametersAreInOrder($code_base, $method);
        $minimum_target_php_version = Config::get_closest_minimum_target_php_version_id();
        if ($minimum_target_php_version < 70200 && !$method->isFromPHPDoc()) {
            self::analyzeRealSignatureCompatibility($code_base, $method, $minimum_target_php_version);
        }

        // Look at each parameter to make sure their types
        // are valid
        $is_optional_seen = false;
        foreach ($method->getParameterList() as $i => $parameter) {
            if ($parameter->getFlags() & Parameter::PARAM_MODIFIER_VISIBILITY_FLAGS) {
                if ($method instanceof Method && strcasecmp($method->getName(), '__construct') === 0) {
                    Issue::maybeEmit(
                        $code_base,
                        $parameter->createContext($method),
                        Issue::CompatibleConstructorPropertyPromotion,
                        $parameter->getFileRef()->getLineNumberStart(),
                        $parameter,
                        $method->getRepresentationForIssue(true)
                    );
                } else {
                    // emit an InvalidNode warning for non-constructors (closures, global functions, other methods)
                    Issue::maybeEmit(
                        $code_base,
                        $parameter->createContext($method),
                        Issue::InvalidNode,
                        $parameter->getFileRef()->getLineNumberStart(),
                        "Cannot use visibility modifier on parameter $parameter of non-constructor " . $method->getRepresentationForIssue(true)
                    );
                }
            }
            $union_type = $parameter->getUnionType();

            if ($parameter->isOptional()) {
                $is_optional_seen = true;
            } else {
                if ($is_optional_seen) {
                    Issue::maybeEmit(
                        $code_base,
                        $method->getContext(),
                        Issue::ParamReqAfterOpt,
                        $parameter->getFileRef()->getLineNumberStart(),
                        '(' . $parameter->toStubString() . ')',
                        '(' . $method->getParameterList()[$i - 1]->toStubString() . ')'
                    );
                }
            }

            // Look at each type in the parameter's Union Type
            foreach ($union_type->getReferencedClasses() as $outer_type => $type) {
                // If it's a reference to self, its OK
                if ($method instanceof Method && $type instanceof StaticOrSelfType) {
                    continue;
                }

                if ($type instanceof TemplateType) {
                    if ($method instanceof Method) {
                        if ($method->isStatic() && !$method->declaresTemplateTypeInComment($type)) {
                            Issue::maybeEmit(
                                $code_base,
                                $method->getContext(),
                                Issue::TemplateTypeStaticMethod,
                                $parameter->getFileRef()->getLineNumberStart(),
                                (string)$method->getFQSEN()
                            );
                        }
                    }
                } else {
                    // Make sure the class exists
                    $type_fqsen = FullyQualifiedClassName::fromType($type);
                    if (!$code_base->hasClassWithFQSEN($type_fqsen)) {
                        Issue::maybeEmitWithParameters(
                            $code_base,
                            $method->getContext(),
                            Issue::UndeclaredTypeParameter,
                            $parameter->getFileRef()->getLineNumberStart(),
                            [$parameter->getName(), (string)$outer_type],
                            IssueFixSuggester::suggestSimilarClass(
                                $code_base,
                                $method->getContext(),
                                $type_fqsen,
                                null,
                                IssueFixSuggester::DEFAULT_CLASS_SUGGESTION_PREFIX,
                                IssueFixSuggester::CLASS_SUGGEST_CLASSES_AND_TYPES
                            )
                        );
                    } elseif ($code_base->hasClassWithFQSEN($type_fqsen->withAlternateId(1))) {
                        UnionType::emitRedefinedClassReferenceWarning(
                            $code_base,
                            (clone($method->getContext()))->withLineNumberStart($parameter->getFileRef()->getLineNumberStart()),
                            $type_fqsen
                        );
                    }
                }
            }
        }
        foreach ($method->getRealParameterList() as $parameter) {
            if ($parameter->hasDefaultValue()) {
                $default_node = $parameter->getDefaultValue();
                if ($default_node instanceof Node &&
                        !$parameter->getUnionType()->containsNullableOrIsEmpty() &&
                        $parameter->getDefaultValueType()->isNull()) {
                    // @phan-suppress-next-next-line PhanPartialTypeMismatchArgumentInternal
                    if (!($default_node->kind === ast\AST_CONST &&
                            \strtolower($default_node->children['name']->children['name'] ?? '') === 'null')) {
                        Issue::maybeEmit(
                            $code_base,
                            $method->getContext(),
                            Issue::CompatibleDefaultEqualsNull,
                            $default_node->lineno,
                            ASTReverter::toShortString($default_node),
                            $parameter->getUnionType() . ' $' . $parameter->getName()
                        );
                    }
                }
            }
            $union_type = $parameter->getUnionType();

            foreach ($union_type->getTypeSet() as $type) {
                if (!$type->isObjectWithKnownFQSEN()) {
                    continue;
                }
                $type_fqsen = FullyQualifiedClassName::fromType($type);
                if (!$code_base->hasClassWithFQSEN($type_fqsen)) {
                    // We should have already warned
                    continue;
                }
                $class = $code_base->getClassByFQSEN($type_fqsen);
                if ($class->isTrait()) {
                    Issue::maybeEmit(
                        $code_base,
                        $method->getContext(),
                        Issue::TypeInvalidTraitParam,
                        $parameter->getFileRef()->getLineNumberStart(),
                        $method->getNameForIssue(),
                        $parameter->getName(),
                        $type_fqsen->__toString()
                    );
                }
            }
        }

        if ($method instanceof Method) {
            if ($method->getName() === '__construct') {
                $class = $method->getClass($code_base);
                if ($class->isGeneric()) {
                    $class->hydrate($code_base);
                    // Call this to emit any warnings about missing template params
                    $class->getGenericConstructorBuilder($code_base);
                }
            }
            self::analyzeOverrideSignature($code_base, $method);
        }
    }

    /**
     * Precondition: $minimum_target_php_version < 70200
     */
    private static function analyzeRealSignatureCompatibility(CodeBase $code_base, FunctionInterface $method, int $minimum_target_php_version): void
    {
        $php70_checks = $minimum_target_php_version < 70100;

        foreach ($method->getRealParameterList() as $real_parameter) {
            foreach ($real_parameter->getUnionType()->getTypeSet() as $type) {
                $type_class = \get_class($type);
                if ($php70_checks) {
                    if ($type->isNullable()) {
                        if ($real_parameter->isUsingNullableSyntax()) {
                            Issue::maybeEmit(
                                $code_base,
                                $method->getContext(),
                                Issue::CompatibleNullableTypePHP70,
                                $real_parameter->getFileRef()->getLineNumberStart(),
                                (string)$type
                            );
                        }
                    }
                    if ($type_class === IterableType::class) {
                        Issue::maybeEmit(
                            $code_base,
                            $method->getContext(),
                            Issue::CompatibleIterableTypePHP70,
                            $real_parameter->getFileRef()->getLineNumberStart(),
                            (string)$type
                        );
                        continue;
                    }
                    if ($minimum_target_php_version < 70000 && $type instanceof ScalarType) {
                        Issue::maybeEmit(
                            $code_base,
                            $method->getContext(),
                            Issue::CompatibleScalarTypePHP56,
                            $real_parameter->getFileRef()->getLineNumberStart(),
                            (string)$type
                        );
                    }
                }
                if ($type_class === ObjectType::class) {
                    if ($minimum_target_php_version < 70200) {
                        Issue::maybeEmit(
                            $code_base,
                            $method->getContext(),
                            Issue::CompatibleObjectTypePHP71,
                            $real_parameter->getFileRef()->getLineNumberStart(),
                            (string)$type
                        );
                    }
                } elseif ($type_class === MixedType::class) {
                    if ($minimum_target_php_version < 80000) {
                        Issue::maybeEmit(
                            $code_base,
                            $method->getContext(),
                            Issue::CompatibleMixedType,
                            $real_parameter->getFileRef()->getLineNumberStart(),
                            (string)$type
                        );
                    }
                }
            }
        }
        foreach ($method->getRealReturnType()->getTypeSet() as $type) {
            $type_class = \get_class($type);
            if ($php70_checks) {
                if ($minimum_target_php_version < 70000) {
                    Issue::maybeEmit(
                        $code_base,
                        $method->getContext(),
                        Issue::CompatibleAnyReturnTypePHP56,
                        $method->getFileRef()->getLineNumberStart(),
                        (string)$method->getRealReturnType()
                    );
                }
                // Could check for use statements, but `php7.1 -l path/to/file.php` would do that already.
                if ($minimum_target_php_version < 70100) {
                    if ($type_class === VoidType::class) {
                        Issue::maybeEmit(
                            $code_base,
                            $method->getContext(),
                            Issue::CompatibleVoidTypePHP70,
                            $method->getFileRef()->getLineNumberStart(),
                            (string)$type
                        );
                    } else {
                        if ($type->isNullable()) {
                            // Don't emit CompatibleNullableTypePHP70 for `void`.
                                Issue::maybeEmit(
                                    $code_base,
                                    $method->getContext(),
                                    Issue::CompatibleNullableTypePHP70,
                                    $method->getFileRef()->getLineNumberStart(),
                                    (string)$type
                                );
                        }
                        if ($type_class === IterableType::class) {
                            Issue::maybeEmit(
                                $code_base,
                                $method->getContext(),
                                Issue::CompatibleIterableTypePHP70,
                                $method->getFileRef()->getLineNumberStart(),
                                (string)$type
                            );
                            continue;
                        }
                        if ($minimum_target_php_version < 70000 && $type instanceof ScalarType) {
                            Issue::maybeEmit(
                                $code_base,
                                $method->getContext(),
                                Issue::CompatibleScalarTypePHP56,
                                $method->getFileRef()->getLineNumberStart(),
                                (string)$type
                            );
                        }
                    }
                }
            }
            if ($type_class === ObjectType::class) {
                if ($minimum_target_php_version < 70200) {
                    Issue::maybeEmit(
                        $code_base,
                        $method->getContext(),
                        Issue::CompatibleObjectTypePHP71,
                        $method->getFileRef()->getLineNumberStart(),
                        (string)$type
                    );
                }
            } elseif ($type_class === MixedType::class) {
                if ($minimum_target_php_version < 80000) {
                    Issue::maybeEmit(
                        $code_base,
                        $method->getContext(),
                        Issue::CompatibleMixedType,
                        $method->getFileRef()->getLineNumberStart(),
                        (string)$type
                    );
                }
            }
        }
    }

    private static function checkCommentParametersAreInOrder(CodeBase $code_base, FunctionInterface $method): void
    {
        $comment = $method->getComment();
        if ($comment === null) {
            return;
        }
        $parameter_map = $comment->getParameterMap();
        if (\count($parameter_map) < 2) {
            // There have to be at least two comment parameters for the parameters to be out of order
            return;
        }
        $prev_index = -1;
        $prev_name = -1;
        $comment_parameter_map = $comment->getParameterMap();
        $expected_parameter_order = \array_flip(\array_keys($comment_parameter_map));
        foreach ($method->getParameterList() as $parameter) {
            $parameter_name = $parameter->getName();
            $parameter_index_in_comment = $expected_parameter_order[$parameter_name] ?? null;
            if ($parameter_index_in_comment === null) {
                continue;
            }
            if ($parameter_index_in_comment < $prev_index) {
                $comment_param = $comment_parameter_map[$parameter_name] ?? null;
                $line = $comment_param ? $comment_param->getLineno() : $method->getFileRef()->getLineNumberStart();
                Issue::maybeEmit(
                    $code_base,
                    $method->getContext(),
                    Issue::CommentParamOutOfOrder,
                    $line,
                    $prev_name,
                    $parameter_name
                );
                return;
            }
            $prev_name = $parameter_name;
            $prev_index = $parameter_index_in_comment;
        }
    }

    /**
     * Make sure signatures line up between methods and the
     * methods they override
     *
     * @see https://en.wikipedia.org/wiki/Liskov_substitution_principle
     */
    private static function analyzeOverrideSignature(
        CodeBase $code_base,
        Method $method
    ): void {
        if (!Config::getValue('analyze_signature_compatibility')) {
            return;
        }

        // Hydrate the class this method is coming from in
        // order to understand if it's an override or not
        $class = $method->getClass($code_base);
        $class->hydrate($code_base);

        // Check to see if the method is an override
        // $method->analyzeOverride($code_base);

        // Make sure we're actually overriding something
        // TODO(in another PR): check that signatures of magic methods are valid, if not done already (e.g. __get expects one param, most can't define return types, etc.)?
        $is_actually_override = $method->isOverride();

        if (!$is_actually_override && $method->isOverrideIntended()) {
            self::analyzeOverrideComment($code_base, $method);
        }

        if (!$is_actually_override) {
            return;
        }

        // Get the method(s) that are being overridden
        // E.g. if the subclass, the parent class, and an interface the subclass implements implement a method,
        //      then this has to check two different overrides (Subclass overriding parent class, and subclass overriding abstract method in interface)
        try {
            $o_method_list = $method->getOverriddenMethods($code_base);
        } catch (CodeBaseException $_) {
            if (strcasecmp($method->getDefiningFQSEN()->getName(), $method->getFQSEN()->getName()) !== 0) {
                // Give up, this is probably a renamed trait method that overrides another trait method.
                return;
            }
            // TODO: Remove if no edge cases are seen.
            Issue::maybeEmit(
                $code_base,
                $method->getContext(),
                Issue::UnanalyzableInheritance,
                $method->getFileRef()->getLineNumberStart(),
                $method->getFQSEN()
            );
            return;
        }
        foreach ($o_method_list as $o_method) {
            self::analyzeOverrideSignatureForOverriddenMethod($code_base, $method, $class, $o_method);
        }
    }

    private static function analyzeOverrideComment(CodeBase $code_base, Method $method): void
    {
        if ($method->isMagic()) {
            return;
        }
        // Only emit this issue on the base class, not for the subclass which inherited it
        if ($method->getDefiningFQSEN() !== $method->getFQSEN()) {
            return;
        }
        Issue::maybeEmit(
            $code_base,
            $method->getContext(),
            Issue::CommentOverrideOnNonOverrideMethod,
            $method->getFileRef()->getLineNumberStart(),
            $method->getFQSEN()
        );
    }

    /**
     * Make sure signatures line up between methods and a method it overrides.
     *
     * @see https://en.wikipedia.org/wiki/Liskov_substitution_principle
     *
     * @param CodeBase $code_base
     * @param Method $method the overriding method.
     * @param Clazz  $class the subclass where the overrides take place.
     * @param Method $o_method the overridden method.
     */
    private static function analyzeOverrideSignatureForOverriddenMethod(
        CodeBase $code_base,
        Method $method,
        Clazz $class,
        Method $o_method
    ): void {
        if ($o_method->isFinal()) {
            // Even if it is a constructor, verify that a method doesn't override a final method.
            // TODO: different warning for trait (#1126)
            self::warnOverridingFinalMethod($code_base, $method, $class, $o_method);
        }

        $construct_access_signature_mismatch_thrown = false;
        if ($method->getName() === '__construct') {
            // flip the switch on so we don't throw both ConstructAccessSignatureMismatch now and AccessSignatureMismatch later
            $construct_access_signature_mismatch_thrown = Config::get_closest_minimum_target_php_version_id() < 70200 && !$o_method->getPhanFlagsHasState(Flags::IS_FAKE_CONSTRUCTOR) && $o_method->isStrictlyMoreVisibleThan($method);

            if ($construct_access_signature_mismatch_thrown) {
                Issue::maybeEmit(
                    $code_base,
                    $method->getContext(),
                    Issue::ConstructAccessSignatureMismatch,
                    $method->getFileRef()->getLineNumberStart(),
                    $method,
                    $o_method,
                    $o_method->getFileRef()->getFile(),
                    $o_method->getFileRef()->getLineNumberStart()
                );
            }

            if (!$o_method->isAbstract()) {
                return;
            }
        }

        // Don't bother warning about incompatible signatures for private methods.
        // (But it is an error to override a private final method)
        if ($o_method->isPrivate()) {
            return;
        }
        // Inherit (at)phan-pure annotations by default.
        if ($o_method->isPure()) {
            $method->setIsPure();
        }

        // Get the class that the overridden method lives on
        $o_class = $o_method->getClass($code_base);

        // A lot of analyzeOverrideRealSignature is redundant.
        // However, phan should consistently emit both issue types if one of them is suppressed.
        self::analyzeOverrideRealSignature($code_base, $method, $class, $o_method, $o_class);

        // Phan needs to complain in some cases, such as a trait existing for an abstract method defined in the class.
        // PHP also checks if a trait redefines a method in the class.
        if ($o_class->isTrait() && $method->getDefiningFQSEN()->getFullyQualifiedClassName() === $class->getFQSEN()) {
            // Give up on analyzing if the class **directly** overrides any (abstract OR non-abstract) method defined by the trait
            // TODO: Fix edge cases caused by hack changing FQSEN of private methods
            return;
        }
        $mismatch_details = '';

        // Get the parameters for that method
        $o_parameter_list = $o_method->getParameterList();

        // If we have a parent type defined, map the method's
        // return type and parameter types through it
        $type_option = $class->getParentTypeOption();

        // Map overridden method parameter types through any
        // template type parameters we may have
        if ($type_option->isDefined()) {
            $o_parameter_list =
                \array_map(static function (Parameter $parameter) use ($type_option, $code_base): Parameter {

                    if (!$parameter->getUnionType()->hasTemplateTypeRecursive()) {
                        return $parameter;
                    }

                    $mapped_parameter = clone($parameter);

                    $mapped_parameter->setUnionType(
                        $mapped_parameter->getUnionType()->withTemplateParameterTypeMap(
                            $type_option->get()->getTemplateParameterTypeMap(
                                $code_base
                            )
                        )
                    );

                    return $mapped_parameter;
                }, $o_parameter_list);
        }

        // Map overridden method return type through any template
        // type parameters we may have
        $o_return_union_type = $o_method->getUnionType();
        if ($type_option->isDefined()
            && $o_return_union_type->hasTemplateTypeRecursive()
        ) {
            $o_return_union_type =
                $o_return_union_type->withTemplateParameterTypeMap(
                    $type_option->get()->getTemplateParameterTypeMap(
                        $code_base
                    )
                );
        }

        // Determine if the signatures match up
        $signatures_match = true;

        // Make sure the count of parameters matches
        if ($method->getNumberOfRequiredParameters()
            > $o_method->getNumberOfRequiredParameters()
        ) {
            $signatures_match = false;
            $mismatch_details = 'Saw more required parameters in the override';
        } elseif ($method->getNumberOfParameters()
            < $o_method->getNumberOfParameters()
        ) {
            $signatures_match = false;
            $mismatch_details = 'Saw fewer optional parameters in the override';

        // If parameter counts match, check their types
        } else {
            $real_parameter_list = $method->getRealParameterList();
            $o_real_parameter_list = $o_method->getRealParameterList();

            foreach ($method->getParameterList() as $i => $parameter) {
                if (!isset($o_parameter_list[$i])) {
                    continue;
                }

                $o_parameter = $o_parameter_list[$i];

                // Changing pass by reference is not ok
                // @see https://3v4l.org/Utuo8
                if ($parameter->isPassByReference() != $o_parameter->isPassByReference()) {
                    $signatures_match = false;
                    $mismatch_details = "Difference in passing by reference in override $parameter of parameter $o_parameter";
                    break;
                }

                // Variadic parameters must match up.
                if ($o_parameter->isVariadic() !== $parameter->isVariadic()) {
                    $signatures_match = false;
                    $mismatch_details = "Difference in being variadic in override $parameter of parameter $o_parameter";
                    break;
                }

                // Check for the presence of real types first, warn if the override has a type but the original doesn't.
                $o_real_parameter = $o_real_parameter_list[$i] ?? null;
                $real_parameter = $real_parameter_list[$i] ?? null;
                if ($o_real_parameter !== null && $real_parameter !== null && !$real_parameter->getUnionType()->isEmptyOrMixed() && $o_real_parameter->getUnionType()->isEmptyOrMixed()
                    && (!$method->isFromPHPDoc() || $parameter->getUnionType()->isEmptyOrMixed())) {
                    $signatures_match = false;
                    $mismatch_details = "Cannot use $parameter with a real type to override parameter $o_parameter without a real type";
                    break;
                }

                // A stricter type on an overriding method is cool
                if ($parameter->getUnionType()->isEmpty()
                    || $parameter->getUnionType()->isType(MixedType::instance(false))
                ) {
                    continue;
                }

                if ($o_parameter->getUnionType()->isEmpty() || $o_parameter->getUnionType()->isType(MixedType::instance(false))) {
                    continue;
                }

                // In php 7.2, it's ok to have a more relaxed type on an overriding method.
                // In earlier versions it isn't.
                // Because this check is analyzing phpdoc types, so it's fine for php < 7.2 as well. Use `PhanParamSignatureRealMismatch*` for detecting **real** mismatches.
                //
                // https://3v4l.org/XTm3P

                // If we have types, make sure they line up
                //
                // TODO: should we be expanding the types on $o_parameter
                //       via ->asExpandedTypes($code_base)?
                //
                //       @see https://3v4l.org/ke3kp
                if (!self::canWeakCast($code_base, $o_parameter->getUnionType(), $parameter->getUnionType())) {
                    $signatures_match = false;
                    $mismatch_details = "Expected $parameter to have the same type as $o_parameter or a supertype";
                    break;
                }
            }
        }

        // Return types should be mappable for LSP
        // Note: PHP requires return types to be identical
        // The return type should be stricter than or identical to the overridden union type.
        // E.g. there is no issue if the overridden return type is empty.
        // See https://github.com/phan/phan/issues/1397
        if (!$o_return_union_type->isEmptyOrMixed()) {
            if (!$method->getUnionType()->asExpandedTypes($code_base)->canCastToUnionType(
                $o_return_union_type
            )) {
                $signatures_match = false;
            }
        }

        // Static or non-static should match
        if ($method->isStatic() != $o_method->isStatic()) {
            if ($o_method->isStatic()) {
                Issue::maybeEmit(
                    $code_base,
                    $method->getContext(),
                    Issue::AccessStaticToNonStatic,
                    $method->getFileRef()->getLineNumberStart(),
                    $o_method->getFQSEN()
                );
            } else {
                Issue::maybeEmit(
                    $code_base,
                    $method->getContext(),
                    Issue::AccessNonStaticToStatic,
                    $method->getFileRef()->getLineNumberStart(),
                    $o_method->getFQSEN()
                );
            }
        }


        if ($o_method->returnsRef() && !$method->returnsRef()) {
            $signatures_match = false;
        }

        if (!$signatures_match) {
            if ($o_method->isPHPInternal()) {
                Issue::maybeEmit(
                    $code_base,
                    $method->getContext(),
                    Issue::ParamSignatureMismatchInternal,
                    $method->getFileRef()->getLineNumberStart(),
                    $method,
                    $o_method,
                    $mismatch_details !== '' ? " ($mismatch_details)" : ''
                );
            } else {
                Issue::maybeEmit(
                    $code_base,
                    $method->getContext(),
                    Issue::ParamSignatureMismatch,
                    $method->getFileRef()->getLineNumberStart(),
                    $method,
                    $o_method,
                    $o_method->getFileRef()->getFile(),
                    $o_method->getFileRef()->getLineNumberStart(),
                    $mismatch_details !== '' ? " ($mismatch_details)" : ''
                );
            }
        }

        // Access must be compatible
        if (!$construct_access_signature_mismatch_thrown && $o_method->isStrictlyMoreVisibleThan($method)) {
            if ($o_method->isPHPInternal()) {
                Issue::maybeEmit(
                    $code_base,
                    $method->getContext(),
                    Issue::AccessSignatureMismatchInternal,
                    $method->getFileRef()->getLineNumberStart(),
                    $method,
                    $o_method
                );
            } else {
                Issue::maybeEmit(
                    $code_base,
                    $method->getContext(),
                    Issue::AccessSignatureMismatch,
                    $method->getFileRef()->getLineNumberStart(),
                    $method,
                    $o_method,
                    $o_method->getFileRef()->getFile(),
                    $o_method->getFileRef()->getLineNumberStart()
                );
            }
        }
    }

    private static function canWeakCast(CodeBase $code_base, UnionType $overridden_type, UnionType $type): bool
    {
        $expanded_overridden_type = $overridden_type->asExpandedTypes($code_base);
        return $expanded_overridden_type->canCastToUnionType($type) &&
                    $expanded_overridden_type->hasAnyTypeOverlap($code_base, $type);
    }
    /**
     * Previously, Phan bases the analysis off of phpdoc.
     * Keeping that around(e.g. to check that string[] is compatible with string[])
     * and also checking the **real**(non-phpdoc) types.
     *
     * @param $code_base
     * @param $method - The overriding method
     * @param $o_method - The overridden method. E.g. if a subclass overrid a base class implementation, then $o_method would be from the base class.
     * @param $o_class the overridden class
     */
    private static function analyzeOverrideRealSignature(
        CodeBase $code_base,
        Method $method,
        Clazz $class,
        Method $o_method,
        Clazz $o_class
    ): void {
        if ($o_class->isTrait() && $method->getDefiningFQSEN()->getFullyQualifiedClassName() === $class->getFQSEN()) {
            // Give up on analyzing if the class **directly** overrides any (abstract OR non-abstract) method defined by the trait
            // TODO: Fix edge cases caused by hack changing FQSEN of private methods
            return;
        }

        // Get the parameters for that method
        // NOTE: If the overriding method is from an (at)method tag, then compare the phpdoc types instead here to emit FromPHPDoc issue equivalents.
        // TODO: Track magic and real methods separately so that subclasses of subclasses get properly analyzed
        $o_parameter_list = $method->isFromPHPDoc() ? $o_method->getParameterList() : $o_method->getRealParameterList();

        // Make sure the count of parameters matches
        if ($method->getNumberOfRequiredRealParameters()
            > $o_method->getNumberOfRequiredRealParameters()
        ) {
            self::emitSignatureRealMismatchIssue(
                $code_base,
                $method,
                $o_method,
                Issue::ParamSignatureRealMismatchTooManyRequiredParameters,
                Issue::ParamSignatureRealMismatchTooManyRequiredParametersInternal,
                Issue::ParamSignaturePHPDocMismatchTooManyRequiredParameters,
                null,
                $method->getNumberOfRequiredRealParameters(),
                $o_method->getNumberOfRequiredRealParameters()
            );
            return;
        } elseif ($method->getNumberOfRealParameters()
            < $o_method->getNumberOfRealParameters()
        ) {
            self::emitSignatureRealMismatchIssue(
                $code_base,
                $method,
                $o_method,
                Issue::ParamSignatureRealMismatchTooFewParameters,
                Issue::ParamSignatureRealMismatchTooFewParametersInternal,
                Issue::ParamSignaturePHPDocMismatchTooFewParameters,
                null,
                $method->getNumberOfRealParameters(),
                $o_method->getNumberOfRealParameters()
            );
            return;
            // If parameter counts match, check their types
        }
        $is_possibly_compatible = true;

        // TODO: Stricter checks for parameter types when this is a magic method?
        // - If the overriding method is magic, then compare the magic method phpdoc types against the phpdoc+real types  of the parent
        foreach ($method->isFromPHPDoc() ? $method->getParameterList() : $method->getRealParameterList() as $i => $parameter) {
            $offset = $i + 1;
            // TODO: check if variadic
            if (!isset($o_parameter_list[$i])) {
                continue;
            }

            // TODO: check that the variadic types match up?
            $o_parameter = $o_parameter_list[$i];

            // Changing pass by reference is not ok
            // @see https://3v4l.org/Utuo8
            if ($parameter->isPassByReference() != $o_parameter->isPassByReference()) {
                $is_reference = $parameter->isPassByReference();
                self::emitSignatureRealMismatchIssue(
                    $code_base,
                    $method,
                    $o_method,
                    ($is_reference ? Issue::ParamSignatureRealMismatchParamIsReference         : Issue::ParamSignatureRealMismatchParamIsNotReference),
                    ($is_reference ? Issue::ParamSignatureRealMismatchParamIsReferenceInternal : Issue::ParamSignatureRealMismatchParamIsNotReferenceInternal),
                    ($is_reference ? Issue::ParamSignaturePHPDocMismatchParamIsReference       : Issue::ParamSignaturePHPDocMismatchParamIsNotReference),
                    self::guessCommentParamLineNumber($method, $parameter),
                    $offset
                );
                return;
            }

            // Changing variadic to/from non-variadic is not ok?
            // (Not absolutely sure about that)
            if ($parameter->isVariadic() != $o_parameter->isVariadic()) {
                $is_variadic = $parameter->isVariadic();
                self::emitSignatureRealMismatchIssue(
                    $code_base,
                    $method,
                    $o_method,
                    ($is_variadic ? Issue::ParamSignatureRealMismatchParamVariadic         : Issue::ParamSignatureRealMismatchParamNotVariadic),
                    ($is_variadic ? Issue::ParamSignatureRealMismatchParamVariadicInternal : Issue::ParamSignatureRealMismatchParamNotVariadicInternal),
                    ($is_variadic ? Issue::ParamSignaturePHPDocMismatchParamVariadic       : Issue::ParamSignaturePHPDocMismatchParamNotVariadic),
                    self::guessCommentParamLineNumber($method, $parameter),
                    $offset
                );
                return;
            }

            // Either 0 or both of the params must have types for the signatures to be compatible.
            $o_parameter_union_type = $o_parameter->getUnionType();
            $parameter_union_type = $parameter->getUnionType();
            // Mixed and empty parameter types are interchangeable in php 8
            if ($parameter_union_type->isEmptyOrMixed() != $o_parameter_union_type->isEmptyOrMixed()) {
                if ($parameter_union_type->isEmptyOrMixed()) {
                    // Don't warn about mixed
                    if (Config::getValue('allow_method_param_type_widening') === false) {
                        $is_possibly_compatible = false;
                        self::emitSignatureRealMismatchIssue(
                            $code_base,
                            $method,
                            $o_method,
                            Issue::ParamSignatureRealMismatchHasNoParamType,
                            Issue::ParamSignatureRealMismatchHasNoParamTypeInternal,
                            Issue::ParamSignaturePHPDocMismatchHasNoParamType,
                            self::guessCommentParamLineNumber($method, $parameter),
                            $offset,
                            (string)$o_parameter_union_type
                        );
                    }
                    continue;
                } else {
                    $is_possibly_compatible = false;
                    self::emitSignatureRealMismatchIssue(
                        $code_base,
                        $method,
                        $o_method,
                        Issue::ParamSignatureRealMismatchHasParamType,
                        Issue::ParamSignatureRealMismatchHasParamTypeInternal,
                        Issue::ParamSignaturePHPDocMismatchHasParamType,
                        self::guessCommentParamLineNumber($method, $parameter),
                        $offset,
                        (string)$parameter_union_type
                    );
                    continue;
                }
            }

            // If both have types, make sure they are identical.
            // Non-nullable param types can be substituted with the nullable equivalents.
            // E.g. A::foo(?int $x) can override BaseClass::foo(int $x)
            if (!$parameter_union_type->isEmptyOrMixed()) {
                if (!$o_parameter_union_type->isEqualTo($parameter_union_type) &&
                    !($parameter_union_type->containsNullable() && $o_parameter_union_type->isEqualTo($parameter_union_type->nonNullableClone()))
                ) {
                    // There is one exception to this in php 7.1 - the pseudo-type "iterable" can replace ArrayAccess/array in a subclass
                    // TODO: Traversable and array work, but Iterator doesn't. Check for those specific cases?
                    $is_exception_to_rule = $parameter_union_type->hasIterable() &&
                        $o_parameter_union_type->hasIterable() &&
                        ($parameter_union_type->hasType(IterableType::instance(true)) ||
                         $parameter_union_type->hasType(IterableType::instance(false)) && !$o_parameter_union_type->containsNullable());

                    if (!$is_exception_to_rule) {
                        $is_possibly_compatible = false;
                        self::emitSignatureRealMismatchIssue(
                            $code_base,
                            $method,
                            $o_method,
                            Issue::ParamSignatureRealMismatchParamType,
                            Issue::ParamSignatureRealMismatchParamTypeInternal,
                            Issue::ParamSignaturePHPDocMismatchParamType,
                            self::guessCommentParamLineNumber($method, $parameter),
                            $offset,
                            (string)$parameter_union_type,
                            (string)$o_parameter_union_type
                        );
                        continue;
                    }
                }
            }
        }

        $o_return_union_type = $o_method->getRealReturnType();

        $return_union_type = $method->isFromPHPDoc() ? $method->getUnionType() : $method->getRealReturnType();
        // If the parent has a return type, then return types should be equal.
        // A non-nullable return type can override a nullable return type of the same type.
        // Be sure to handle `void`, which contains nullable types
        if (!$o_return_union_type->isEmpty()) {
            if (!($o_return_union_type->isEqualTo($return_union_type) || (
                ($o_return_union_type->containsNullable() && !$o_return_union_type->isNull()) && ($o_return_union_type->nonNullableClone()->isEqualTo($return_union_type)))
                )) {
                // There is one exception to this in php 7.1 - the pseudo-type "iterable" can replace ArrayAccess/array in a subclass
                // TODO: Traversable and array work, but Iterator doesn't. Check for those specific cases?
                $is_exception_to_rule = $return_union_type->hasIterable() &&
                    $o_return_union_type->hasIterable() &&
                    ($o_return_union_type->hasType(IterableType::instance(true)) ||
                     $o_return_union_type->hasType(IterableType::instance(false)) && !$return_union_type->containsNullable());
                if (!$is_exception_to_rule) {
                    $is_possibly_compatible = false;

                    self::emitSignatureRealMismatchIssue(
                        $code_base,
                        $method,
                        $o_method,
                        Issue::ParamSignatureRealMismatchReturnType,
                        Issue::ParamSignatureRealMismatchReturnTypeInternal,
                        Issue::ParamSignaturePHPDocMismatchReturnType,
                        null,
                        (string)$return_union_type,
                        (string)$o_return_union_type
                    );
                }
            }
        }
        if ($is_possibly_compatible) {
            if (Config::getValue('inherit_phpdoc_types')) {
                self::inheritPHPDoc($code_base, $method, $o_method);
            }
        }
    }

    /**
     * @return array<string,CommentParameter>
     */
    private static function extractCommentParameterMap(Method $method): array
    {
        $comment = $method->getComment();
        return $comment ? $comment->getParameterMap() : [];
    }
    /**
     * Inherit any missing phpdoc types for (at)return and (at)param of $method from $o_method.
     * This is the default behavior, see https://www.phpdoc.org/docs/latest/guides/inheritance.html
     */
    private static function inheritPHPDoc(
        CodeBase $code_base,
        Method $method,
        Method $o_method
    ): void {
        // The method was already from phpdoc.
        if ($method->isFromPHPDoc()) {
            return;
        }
        // Get the parameters for that method
        $phpdoc_parameter_list = $method->getParameterList();
        $o_phpdoc_parameter_list = $o_method->getParameterList();
        $comment_parameter_map = null;
        foreach ($phpdoc_parameter_list as $i => $parameter) {
            $parameter_type = $parameter->getNonVariadicUnionType();
            // If there is already a phpdoc parameter type, then don't bother inheriting the parameter type from $o_method
            if (!$parameter_type->isEmpty()) {
                $comment_parameter_map = $comment_parameter_map ?? self::extractCommentParameterMap($method);
                $comment_parameter = $comment_parameter_map[$parameter->getName()] ?? null;
                if ($comment_parameter) {
                    $comment_parameter_type = $comment_parameter->getUnionType();
                    if (!$comment_parameter_type->isEmpty()) {
                        continue;
                    }
                }
            }
            $parent_parameter = $o_phpdoc_parameter_list[$i] ?? null;
            if ($parent_parameter) {
                $parent_parameter_type = $parent_parameter->getNonVariadicUnionType();
                if ($parent_parameter_type->isEmpty()) {
                    continue;
                }
                if ($parameter_type->isEmpty() || $parent_parameter_type->isExclusivelyNarrowedFormOf($code_base, $parameter_type)) {
                    $parameter->setUnionType($parent_parameter_type->eraseRealTypeSetRecursively());
                }
            }
        }

        $parent_phpdoc_return_type = $o_method->getUnionType();
        if (!$parent_phpdoc_return_type->isEmpty()) {
            $phpdoc_return_type = $method->getUnionType();
            if ($phpdoc_return_type->isEmpty()) {
                $method->setUnionType($parent_phpdoc_return_type);
            } else {
                self::maybeInheritCommentReturnType($code_base, $method, $parent_phpdoc_return_type);
            }
        }
    }

    /**
     * @param Method $method a method which has a union type, but is permitted to inherit a more specific type.
     * @param UnionType $inherited_union_type a non-empty union type
     */
    private static function maybeInheritCommentReturnType(CodeBase $code_base, Method $method, UnionType $inherited_union_type): void
    {
        $comment = $method->getComment();
        if ($comment && $comment->hasReturnUnionType()) {
            if (!$comment->getReturnType()->isEmpty()) {
                // This comment explicitly specified the desired return type.
                // Give up on inheriting
                return;
            }
        }
        if ($inherited_union_type->isExclusivelyNarrowedFormOf($code_base, $method->getUnionType())) {
            $method->setUnionType($inherited_union_type);
        }
    }

    /**
     * Emit an $issue_type instance corresponding to a potential runtime inheritance warning/error
     *
     * @param CodeBase $code_base
     * @param Method $method
     * @param Method $o_method the overridden method
     * @param string $issue_type the ParamSignatureRealMismatch* (issue type if overriding user-defined method)
     * @param string $internal_issue_type the ParamSignatureRealMismatch* (issue type if overriding internal method)
     * @param string $phpdoc_issue_type the ParamSignaturePHPDocMismatch* (issue type if overriding internal method)
     * @param ?int $lineno
     * @param int|string ...$args
     */
    private static function emitSignatureRealMismatchIssue(
        CodeBase $code_base,
        Method $method,
        Method $o_method,
        string $issue_type,
        string $internal_issue_type,
        string $phpdoc_issue_type,
        ?int $lineno,
        ...$args
    ): void {
        if ($method->isFromPHPDoc() || $o_method->isFromPHPDoc()) {
            Issue::maybeEmit(
                $code_base,
                $method->getContext(),
                $phpdoc_issue_type,
                $lineno ?? $method->getFileRef()->getLineNumberStart(),
                $method->toRealSignatureString(),
                $o_method->toRealSignatureString(),
                ...array_merge($args, [
                    $o_method->getFileRef()->getFile(),
                    $o_method->getFileRef()->getLineNumberStart(),
                ])
            );
        } elseif ($o_method->isPHPInternal()) {
            Issue::maybeEmit(
                $code_base,
                $method->getContext(),
                $internal_issue_type,
                $lineno ?? $method->getFileRef()->getLineNumberStart(),
                $method->toRealSignatureString(),
                $o_method->toRealSignatureString(),
                ...$args
            );
        } else {
            Issue::maybeEmit(
                $code_base,
                $method->getContext(),
                $issue_type,
                $lineno ?? $method->getFileRef()->getLineNumberStart(),
                $method->toRealSignatureString(),
                $o_method->toRealSignatureString(),
                ...array_merge($args, [
                    $o_method->getFileRef()->getFile(),
                    $o_method->getFileRef()->getLineNumberStart(),
                ])
            );
        }
    }

    private static function analyzeParameterTypesDocblockSignaturesMatch(
        CodeBase $code_base,
        FunctionInterface $method
    ): void {
        $phpdoc_parameter_map = $method->getPHPDocParameterTypeMap();
        if (\count($phpdoc_parameter_map) === 0) {
            // nothing to check.
            return;
        }
        $real_parameter_list = $method->getRealParameterList();
        foreach ($real_parameter_list as $i => $parameter) {
            $real_param_type = $parameter->getNonVariadicUnionType();
            if ($real_param_type->isEmpty()) {
                continue;
            }
            $phpdoc_param_union_type = $phpdoc_parameter_map[$parameter->getName()] ?? null;
            if ($phpdoc_param_union_type && !$phpdoc_param_union_type->isEmpty()) {
                self::tryToAssignPHPDocTypeToParameter($code_base, $method, $i, $parameter, $real_param_type, $phpdoc_param_union_type);
            }
        }
        self::recordOutputReferences($method);
    }

    private static function tryToAssignPHPDocTypeToParameter(
        CodeBase $code_base,
        FunctionInterface $method,
        int $i,
        Parameter $parameter,
        UnionType $real_param_type,
        UnionType $phpdoc_param_union_type
    ): void {
        $context = $method->getContext();
        $resolved_real_param_type = $real_param_type->withStaticResolvedInContext($context);
        $is_exclusively_narrowed = true;
        foreach ($phpdoc_param_union_type->getTypeSet() as $phpdoc_type) {
            // Make sure that the commented type is a narrowed
            // or equivalent form of the syntax-level declared
            // return type.
            if (!$phpdoc_type->isExclusivelyNarrowedFormOrEquivalentTo(
                $resolved_real_param_type,
                $context,
                $code_base
            )
            ) {
                $is_exclusively_narrowed = false;
                Issue::maybeEmit(
                    $code_base,
                    $context,
                    Issue::TypeMismatchDeclaredParam,
                    self::guessCommentParamLineNumber($method, $parameter) ?: $context->getLineNumberStart(),
                    $parameter->getName(),
                    $method->getName(),
                    $phpdoc_type->__toString(),
                    $real_param_type->__toString()
                );
            }
        }
        // TODO: test edge cases of variadic signatures
        if ($is_exclusively_narrowed && Config::getValue('prefer_narrowed_phpdoc_param_type')) {
            $normalized_phpdoc_param_union_type = self::normalizeNarrowedParamType($phpdoc_param_union_type, $real_param_type);
            if ($normalized_phpdoc_param_union_type) {
                $param_to_modify = $method->getParameterList()[$i] ?? null;
                if ($param_to_modify) {
                    // TODO: Maybe have two different sets of methods for setUnionType and setCallerUnionType, this is easy to mix up for variadics.
                    $param_to_modify->setUnionType($normalized_phpdoc_param_union_type->withRealTypeSet($real_param_type->getRealTypeSet()));
                }
            } else {
                $comment = $method->getComment();
                if ($comment === null) {
                    return;
                }
                // This check isn't urgent to fix, and is specific to nullable casting rules,
                // so use a different issue type.
                $param_name = $parameter->getName();
                Issue::maybeEmit(
                    $code_base,
                    $context,
                    Issue::TypeMismatchDeclaredParamNullable,
                    self::guessCommentParamLineNumber($method, $parameter) ?: $context->getLineNumberStart(),
                    $param_name,
                    $method->getName(),
                    $phpdoc_param_union_type->__toString(),
                    $real_param_type->__toString()
                );
            }
        }
    }

    private static function guessCommentParamLineNumber(FunctionInterface $method, Parameter $param): ?int
    {
        $comment = $method->getComment();
        if ($comment === null) {
            return null;
        }
        $parameter_map = $comment->getParameterMap();
        $comment_param = $parameter_map[$param->getName()] ?? null;
        if (!$comment_param) {
            return null;
        }
        return $comment_param->getLineno();
    }

    /**
     * Guesses the return number of a method's PHPDoc's (at)return statement.
     * Returns null if that could not be found.
     * @internal
     */
    public static function guessCommentReturnLineNumber(FunctionInterface $method): ?int
    {
        $comment = $method->getComment();
        if ($comment === null) {
            return null;
        }
        if (!$comment->hasReturnUnionType()) {
            return null;
        }
        return $comment->getReturnLineno();
    }

    private static function recordOutputReferences(FunctionInterface $method): void
    {
        foreach ($method->getOutputReferenceParamNames() as $output_param_name) {
            foreach ($method->getRealParameterList() as $parameter) {
                // TODO: Emit an issue if the (at)phan-output-reference is on a non-reference (at)param?
                if ($parameter->getName() === $output_param_name && $parameter->isPassByReference()) {
                    $parameter->setIsOutputReference();
                }
            }
        }
    }

    /**
     * Forbid these two types of narrowing:
     * 1. Forbid inferring a type of null from "(at)param null $x" for foo(?int $x = null)
     *    The phpdoc is probably nonsense.
     * 2. Forbid inferring a type of `T` from "(at)param T $x" for foo(?T $x = null)
     *    The phpdoc is probably shorthand.
     *
     * Annotations may be added in the future to support this, e.g. "(at)param T $x (at)phan-not-null"
     *
     * @return ?UnionType
     *         - normalized version of $phpdoc_param_union_type (possibly same object)
     *           if Phan should proceed using phpdoc type instead of real types. (Converting T|null to ?T)
     *         - null if the type is an invalid narrowing, and Phan should warn.
     */
    public static function normalizeNarrowedParamType(UnionType $phpdoc_param_union_type, UnionType $real_param_type): ?UnionType
    {
        // "@param null $x" is almost always a mistake. Forbid it for now.
        // But allow "@param T|null $x"
        $has_null = $phpdoc_param_union_type->hasType(NullType::instance(false));
        if ($has_null && $phpdoc_param_union_type->typeCount() === 1) {
            // "@param null"
            return null;
        }
        if (!$real_param_type->containsNullable() || $phpdoc_param_union_type->containsNullable()) {
            // We already validated that the other casts were supported.
            return $phpdoc_param_union_type;
        }
        if (!$has_null) {
            // Attempting to narrow nullable to non-nullable is usually a mistake, currently not supported.
            return null;
        }
        // Create a clone, converting "T|S|null" to "?T|?S"
        return $phpdoc_param_union_type->nullableClone()->withoutType(NullType::instance(false));
    }

    /**
     * Warns if a method is overriding a final method
     */
    private static function warnOverridingFinalMethod(CodeBase $code_base, Method $method, Clazz $class, Method $o_method): void
    {
        if ($method->isFromPHPDoc()) {
            // TODO: Track phpdoc methods separately from real methods
            if ($class->checkHasSuppressIssueAndIncrementCount(Issue::AccessOverridesFinalMethodPHPDoc)) {
                return;
            }
            Issue::maybeEmit(
                $code_base,
                $method->getContext(),
                Issue::AccessOverridesFinalMethodPHPDoc,
                $method->getFileRef()->getLineNumberStart(),
                $method->getFQSEN(),
                $o_method->getFQSEN(),
                $o_method->getFileRef()->getFile(),
                $o_method->getFileRef()->getLineNumberStart()
            );
        } elseif ($o_method->isPHPInternal()) {
            Issue::maybeEmit(
                $code_base,
                $method->getContext(),
                Issue::AccessOverridesFinalMethodInternal,
                $method->getFileRef()->getLineNumberStart(),
                $method->getFQSEN(),
                $o_method->getFQSEN()
            );
        } else {
            $issue_type = Issue::AccessOverridesFinalMethod;

            try {
                $o_clazz = $o_method->getDefiningClass($code_base);
                if ($o_clazz->isTrait()) {
                    $issue_type = Issue::AccessOverridesFinalMethodInTrait;
                }
            } catch (CodeBaseException $_) {
            }

            Issue::maybeEmit(
                $code_base,
                $method->getContext(),
                $issue_type,
                $method->getFileRef()->getLineNumberStart(),
                $method->getFQSEN(),
                $o_method->getFQSEN(),
                $o_method->getFileRef()->getFile(),
                $o_method->getFileRef()->getLineNumberStart()
            );
        }
    }
}
