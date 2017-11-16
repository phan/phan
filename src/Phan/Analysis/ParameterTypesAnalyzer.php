<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\CodeBaseException;
use Phan\Issue;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\Element\Parameter;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\IterableType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\TemplateType;
use Phan\Language\UnionType;

class ParameterTypesAnalyzer
{

    /**
     * Check method parameters to make sure they're valid
     *
     * @return void
     */
    public static function analyzeParameterTypes(
        CodeBase $code_base,
        FunctionInterface $method
    ) {
        if (Config::getValue('check_docblock_signature_param_type_match')) {
            self::analyzeParameterTypesDocblockSignaturesMatch($code_base, $method);
        }

        // Look at each parameter to make sure their types
        // are valid
        foreach ($method->getParameterList() as $parameter) {
            $union_type = $parameter->getUnionType();

            // Look at each type in the parameter's Union Type
            foreach ($union_type->getTypeSet() as $outer_type) {
                $type = $outer_type;

                while ($type instanceof GenericArrayType) {
                    $type = $type->genericArrayElementType();
                }

                // If its a native type or a reference to
                // self, its OK
                if ($type->isNativeType() || ($method instanceof Method && ($type->isSelfType() || $type->isStaticType()))) {
                    continue;
                }

                if ($type instanceof TemplateType) {
                    if ($method instanceof Method) {
                        if ($method->isStatic()) {
                            Issue::maybeEmit(
                                $code_base,
                                $method->getContext(),
                                Issue::TemplateTypeStaticMethod,
                                $method->getFileRef()->getLineNumberStart(),
                                (string)$method->getFQSEN()
                            );
                        }
                    }
                } else {
                    // Make sure the class exists
                    $type_fqsen = $type->asFQSEN();
                    \assert($type_fqsen instanceof FullyQualifiedClassName, 'non-native types must be class names');
                    if (!$code_base->hasClassWithFQSEN($type_fqsen)) {
                        Issue::maybeEmit(
                            $code_base,
                            $method->getContext(),
                            Issue::UndeclaredTypeParameter,
                            $method->getFileRef()->getLineNumberStart(),
                            (string)$outer_type
                        );
                    }
                }
            }
        }

        if ($method instanceof Method) {
            self::analyzeOverrideSignature($code_base, $method);
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
    ) {
        if (!Config::getValue('analyze_signature_compatibility')) {
            return;
        }

        // Hydrate the class this method is coming from in
        // order to understand if its an override or not
        $class = $method->getClass($code_base);
        $class->hydrate($code_base);

        // Check to see if the method is an override
        // $method->analyzeOverride($code_base);

        // Make sure we're actually overriding something
        // TODO(in another PR): check that signatures of magic methods are valid, if not done already (e.g. __get expects one param, most can't define return types, etc.)?
        $is_actually_override = $method->getIsOverride();

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
        } catch (CodeBaseException $e) {
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

    /**
     * @return void
     */
    private static function analyzeOverrideComment(CodeBase $code_base, Method $method)
    {
        if ($method->getIsMagic()) {
            return;
        }
        // Only emit this issue on the base class, not for the subclass which inherited it
        if ($method->getDefiningFQSEN() !== $method->getFQSEN()) {
            return;
        }
        if ($method->hasSuppressIssue(Issue::CommentOverrideOnNonOverrideMethod)) {
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
     */
    private static function analyzeOverrideSignatureForOverriddenMethod(
        CodeBase $code_base,
        Method $method,
        Clazz $class,
        Method $o_method
    ) {
        if ($o_method->isFinal()) {
            // Even if it is a constructor, verify that a method doesn't override a final method.
            // TODO: different warning for trait (#1126)
            self::warnOverridingFinalMethod($code_base, $method, $class, $o_method);
        }

        // Don't bother warning about incompatible signatures for private methods.
        // (But it is an error to override a private final method)
        if ($o_method->isPrivate()) {
            return;
        }

        // Unless it is an abstract constructor,
        // don't worry about signatures lining up on
        // constructors. We just want to make sure that
        // calling a method on a subclass won't cause
        // a runtime error. We usually know what we're
        // constructing at instantiation time, so there
        // is less of a risk.
        if ($method->getName() == '__construct' && !$o_method->isAbstract()) {
            return;
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

        // Get the parameters for that method
        $o_parameter_list = $o_method->getParameterList();

        // If we have a parent type defined, map the method's
        // return type and parameter types through it
        $type_option = $class->getParentTypeOption();

        // Map overridden method parameter types through any
        // template type parameters we may have
        if ($type_option->isDefined()) {
            $o_parameter_list =
                \array_map(function (Parameter $parameter) use ($type_option, $code_base) : Parameter {

                    if (!$parameter->getUnionType()->hasTemplateType()) {
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
            && $o_return_union_type->hasTemplateType()
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
        } elseif ($method->getNumberOfParameters()
            < $o_method->getNumberOfParameters()
        ) {
            $signatures_match = false;

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
                    break;
                }

                // Variadic parameters must match up.
                if ($o_parameter->isVariadic() !== $parameter->isVariadic()) {
                    $signatures_match = false;
                    break;
                }

                // Check for the presence of real types first, warn if the override has a type but the original doesn't.
                $o_real_parameter = $o_real_parameter_list[$i] ?? null;
                $real_parameter = $real_parameter_list[$i] ?? null;
                if ($o_real_parameter !== null && $real_parameter !== null && !$real_parameter->getUnionType()->isEmpty() && $o_real_parameter->getUnionType()->isEmpty()) {
                    $signatures_match = false;
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
                if (!$o_parameter->getUnionType()->canCastToUnionType(
                    $parameter->getUnionType()
                )) {
                    $signatures_match = false;
                    break;
                }
            }
        }

        // Return types should be mappable for LSP
        // Note: PHP requires return types to be identical
        // The return type should be stricter than or identical to the overridden union type.
        // E.g. there is no issue if the overridden return type is empty.
        if (!$o_return_union_type->isEmpty()) {
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
                if (!$method->hasSuppressIssue(Issue::ParamSignatureMismatchInternal)) {
                    Issue::maybeEmit(
                        $code_base,
                        $method->getContext(),
                        Issue::ParamSignatureMismatchInternal,
                        $method->getFileRef()->getLineNumberStart(),
                        $method,
                        $o_method
                    );
                }
            } else {
                Issue::maybeEmit(
                    $code_base,
                    $method->getContext(),
                    Issue::ParamSignatureMismatch,
                    $method->getFileRef()->getLineNumberStart(),
                    $method,
                    $o_method,
                    $o_method->getFileRef()->getFile(),
                    $o_method->getFileRef()->getLineNumberStart()
                );
            }
        }

        // Access must be compatible
        if ($o_method->isProtected() && $method->isPrivate()
            || $o_method->isPublic() && !$method->isPublic()
        ) {
            if ($o_method->isPHPInternal()) {
                if (!$method->hasSuppressIssue(Issue::AccessSignatureMismatchInternal)) {
                    Issue::maybeEmit(
                        $code_base,
                        $method->getContext(),
                        Issue::AccessSignatureMismatchInternal,
                        $method->getFileRef()->getLineNumberStart(),
                        $method,
                        $o_method
                    );
                }
            } else {
                if (!$method->hasSuppressIssue(Issue::AccessSignatureMismatch)) {
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
     * @return void
     */
    private static function analyzeOverrideRealSignature(
        CodeBase $code_base,
        Method $method,
        Clazz $class,
        Method $o_method,
        Clazz $o_class
    ) {
        if ($o_class->isTrait() && $method->getDefiningFQSEN()->getFullyQualifiedClassName() === $class->getFQSEN()) {
            // Give up on analyzing if the class **directly** overrides any (abstract OR non-abstract) method defined by the trait
            // TODO: Fix edge cases caused by hack changing FQSEN of private methods
            return;
        }

        // Get the parameters for that method
        $o_parameter_list = $o_method->getRealParameterList();

        // Map overridden method return type through any template
        // type parameters we may have
        $o_return_union_type = $o_method->getRealReturnType();

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
                $method->getNumberOfRealParameters(),
                $o_method->getNumberOfRealParameters()
            );
            return;
            // If parameter counts match, check their types
        }
        $is_possibly_compatible = true;

        foreach ($method->getRealParameterList() as $i => $parameter) {
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
                    $offset
                );
                $is_possibly_compatible = false;
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
                    $offset
                );
                $is_possibly_compatible = false;
                return;
            }

            // Either 0 or both of the params must have types for the signatures to be compatible.
            $o_parameter_union_type = $o_parameter->getUnionType();
            $parameter_union_type = $parameter->getUnionType();
            if ($parameter_union_type->isEmpty() != $o_parameter_union_type->isEmpty()) {
                $is_possibly_compatible = false;
                if ($parameter_union_type->isEmpty()) {
                    self::emitSignatureRealMismatchIssue(
                        $code_base,
                        $method,
                        $o_method,
                        Issue::ParamSignatureRealMismatchHasNoParamType,
                        Issue::ParamSignatureRealMismatchHasNoParamTypeInternal,
                        Issue::ParamSignaturePHPDocMismatchHasNoParamType,
                        $offset,
                        (string)$o_parameter_union_type
                    );
                    continue;
                } else {
                    self::emitSignatureRealMismatchIssue(
                        $code_base,
                        $method,
                        $o_method,
                        Issue::ParamSignatureRealMismatchHasParamType,
                        Issue::ParamSignatureRealMismatchHasParamTypeInternal,
                        Issue::ParamSignaturePHPDocMismatchHasParamType,
                        $offset,
                        (string)$parameter_union_type
                    );
                    continue;
                }
            }

            // If both have types, make sure they are identical.
            // Non-nullable param types can be substituted with the nullable equivalents.
            // E.g. A::foo(?int $x) can override BaseClass::foo(int $x)
            if (!$parameter_union_type->isEmpty()) {
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
                            $offset,
                            (string)$parameter_union_type,
                            (string)$o_parameter_union_type
                        );
                        continue;
                    }
                }
            }
        }

        $return_union_type = $method->getRealReturnType();
        // If the parent has a return type, then return types should be equal.
        // A non-nullable return type can override a nullable return type of the same type.
        if (!$o_return_union_type->isEmpty()) {
            if (!($o_return_union_type->isEqualTo($return_union_type) || (
                $o_return_union_type->containsNullable() && !($o_return_union_type->nonNullableClone()->isEqualTo($return_union_type)))
                )) {
                $is_possibly_compatible = false;

                self::emitSignatureRealMismatchIssue(
                    $code_base,
                    $method,
                    $o_method,
                    Issue::ParamSignatureRealMismatchReturnType,
                    Issue::ParamSignatureRealMismatchReturnTypeInternal,
                    Issue::ParamSignaturePHPDocMismatchReturnType,
                    (string)$return_union_type,
                    (string)$o_return_union_type
                );
            }
        }
        if ($is_possibly_compatible) {
            if (Config::getValue('inherit_phpdoc_types')) {
                self::inheritPHPDoc($method, $o_method);
            }
        }
    }

    /**
     * Inherit any missing phpdoc types for (at)return and (at)param of $method from $o_method.
     * This is the default behaviour, see https://www.phpdoc.org/docs/latest/guides/inheritance.html
     *
     * @return void
     */
    private static function inheritPHPDoc(
        Method $method,
        Method $o_method
    ) {
        // Get the parameters for that method
        $phpdoc_parameter_list = $method->getParameterList();
        $o_phpdoc_parameter_list = $o_method->getParameterList();
        foreach ($phpdoc_parameter_list as $i => $parameter) {
            $parameter_type = $parameter->getUnionType();
            if (!$parameter_type->isEmpty()) {
                continue;
            }
            $parent_parameter = $o_phpdoc_parameter_list[$i] ?? null;
            if ($parent_parameter) {
                $parent_parameter_type = $parent_parameter->getUnionType();
                if ($parent_parameter_type->isEmpty()) {
                    continue;
                }
                $parameter->setUnionType(clone($parent_parameter_type));
            }
        }

        $phpdoc_return_type = $method->getUnionType();
        if ($phpdoc_return_type->isEmpty()) {
            $parent_phpdoc_return_type = $o_method->getUnionType();
            if (!$parent_phpdoc_return_type->isEmpty()) {
                $method->setUnionType(clone($parent_phpdoc_return_type));
            }
        }
    }

    /**
     * Emit an
     *
     * @param CodeBase $code_base
     * @param Method $method
     * @param Method $o_method the overridden method
     * @param string $issue_type the ParamSignatureRealMismatch* (issue type if overriding user-defined method)
     * @param string $internal_issue_type the ParamSignatureRealMismatch* (issue type if overriding internal method)
     * @param int|string ...$args
     * @return void
     */
    private static function emitSignatureRealMismatchIssue(CodeBase $code_base, Method $method, Method $o_method, string $issue_type, string $internal_issue_type, string $phpdoc_issue_type, ...$args)
    {
        if ($method->isFromPHPDoc() || $o_method->isFromPHPDoc()) {
            // TODO: for overriding methods defined in phpdoc, going to need to add issue suppressions from the class phpdoc?
            if ($method->hasSuppressIssue($phpdoc_issue_type)) {
                return;
            }
            Issue::maybeEmit(
                $code_base,
                $method->getContext(),
                $phpdoc_issue_type,
                $method->getFileRef()->getLineNumberStart(),
                $method->toRealSignatureString(),
                $o_method->toRealSignatureString(),
                ...array_merge($args, [
                    $o_method->getFileRef()->getFile(),
                    $o_method->getFileRef()->getLineNumberStart(),
                ])
            );
        } elseif ($o_method->isPHPInternal()) {
            if ($method->hasSuppressIssue($internal_issue_type)) {
                return;
            }
            Issue::maybeEmit(
                $code_base,
                $method->getContext(),
                $internal_issue_type,
                $method->getFileRef()->getLineNumberStart(),
                $method->toRealSignatureString(),
                $o_method->toRealSignatureString(),
                ...$args
            );
        } else {
            if ($method->hasSuppressIssue($issue_type)) {
                return;
            }
            Issue::maybeEmit(
                $code_base,
                $method->getContext(),
                $issue_type,
                $method->getFileRef()->getLineNumberStart(),
                $method->toRealSignatureString(),
                $o_method->toRealSignatureString(),
                ...array_merge($args, [
                    $o_method->getFileRef()->getFile(),
                    $o_method->getFileRef()->getLineNumberStart(),
                ])
            );
        }
    }

    /**
     * @return void
     */
    private static function analyzeParameterTypesDocblockSignaturesMatch(
        CodeBase $code_base,
        FunctionInterface $method
    ) {
        $phpdoc_parameter_map = $method->getPHPDocParameterTypeMap();
        if (count($phpdoc_parameter_map) === 0) {
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

    /**
     * @return void
     */
    private static function tryToAssignPHPDocTypeToParameter(
        CodeBase $code_base,
        FunctionInterface $method,
        int $i,
        Parameter $parameter,
        UnionType $real_param_type,
        UnionType $phpdoc_param_union_type
    ) {
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
                if (!$method->hasSuppressIssue(Issue::TypeMismatchDeclaredParam)) {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::TypeMismatchDeclaredParam,
                        $context->getLineNumberStart(),
                        $parameter->getName(),
                        $method->getName(),
                        $phpdoc_type->__toString(),
                        $real_param_type->__toString()
                    );
                }
            }
        }
        // TODO: test edge cases of variadic signatures
        if ($is_exclusively_narrowed && Config::getValue('prefer_narrowed_phpdoc_param_type')) {
            $normalized_phpdoc_param_union_type = self::normalizeNarrowedParamType($phpdoc_param_union_type, $real_param_type);
            if ($normalized_phpdoc_param_union_type) {
                $param_to_modify = $method->getParameterList()[$i] ?? null;
                if ($param_to_modify) {
                    $param_to_modify->setUnionType($normalized_phpdoc_param_union_type);
                }
            } else {
                // This check isn't urgent to fix, and is specific to nullable casting rules,
                // so use a different issue type.
                if (!$method->hasSuppressIssue(Issue::TypeMismatchDeclaredParamNullable)) {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::TypeMismatchDeclaredParamNullable,
                        $context->getLineNumberStart(),
                        $parameter->getName(),
                        $method->getName(),
                        $phpdoc_param_union_type->__toString(),
                        $real_param_type->__toString()
                    );
                }
            }
        }
    }

    /**
     * @param FunctionInterface $method
     */
    private static function recordOutputReferences(FunctionInterface $method)
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
    public static function normalizeNarrowedParamType(UnionType $phpdoc_param_union_type, UnionType $real_param_type)
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
        // Create a clone, converting "T|S|null" to "T|S"
        $phpdoc_param_union_type = $phpdoc_param_union_type->nullableClone();
        $phpdoc_param_union_type->removeType(NullType::instance(false));
        return $phpdoc_param_union_type;
    }

    /**
     * Warns if a method is overriding a final method
     * @return void
     */
    private static function warnOverridingFinalMethod(CodeBase $code_base, Method $method, Clazz $class, Method $o_method)
    {
        if ($method->isFromPHPDoc()) {
            // TODO: Track phpdoc methods separately from real methods
            if ($method->hasSuppressIssue(Issue::AccessOverridesFinalMethodPHPDoc) || $class->hasSuppressIssue(Issue::AccessOverridesFinalMethodPHPDoc)) {
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
            if ($method->hasSuppressIssue(Issue::AccessOverridesFinalMethodInternal)) {
                return;
            }
            Issue::maybeEmit(
                $code_base,
                $method->getContext(),
                Issue::AccessOverridesFinalMethodInternal,
                $method->getFileRef()->getLineNumberStart(),
                $method->getFQSEN(),
                $o_method->getFQSEN()
            );
        } else {
            if ($method->hasSuppressIssue(Issue::AccessOverridesFinalMethod)) {
                return;
            }
            Issue::maybeEmit(
                $code_base,
                $method->getContext(),
                Issue::AccessOverridesFinalMethod,
                $method->getFileRef()->getLineNumberStart(),
                $method->getFQSEN(),
                $o_method->getFQSEN(),
                $o_method->getFileRef()->getFile(),
                $o_method->getFileRef()->getLineNumberStart()
            );
        }
    }
}
