<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\CodeBase;
use Phan\Config;
use Phan\Issue;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\Element\Parameter;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type\IterableType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\TemplateType;

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
        // Look at each parameter to make sure their types
        // are valid
        foreach ($method->getParameterList() as $parameter) {
            $union_type = $parameter->getUnionType();

            // Look at each type in the parameter's Union Type
            foreach ($union_type->getTypeSet() as $type) {

                // If its a native type or a reference to
                // self, its OK
                if ($type->isNativeType() || $type->isSelfType()) {
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
                    assert($type_fqsen instanceof FullyQualifiedClassName, 'non-native types must be class names');
                    if (!$code_base->hasClassWithFQSEN($type_fqsen)) {
                        Issue::maybeEmit(
                            $code_base,
                            $method->getContext(),
                            Issue::UndeclaredTypeParameter,
                            $method->getFileRef()->getLineNumberStart(),
                            (string)$type_fqsen
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
        if (!Config::get()->analyze_signature_compatibility) {
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
        if (!$method->getIsOverride()) {
            return;
        }

        // Get the method that is being overridden
        $o_method = $method->getOverriddenMethod($code_base);

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
                array_map(function (Parameter $parameter) use ($type_option, $code_base) : Parameter {

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
        } else if ($method->getNumberOfParameters()
            < $o_method->getNumberOfParameters()
        ) {
            $signatures_match = false;

        // If parameter counts match, check their types
        } else {
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

                // A stricter type on an overriding method is cool
                // TODO: This doesn't match the definition of LSP.
                // - If you use a stricter param type, you can't call the subclass with args you could call the base class with.
                if ($o_parameter->getUnionType()->isEmpty()
                    || $o_parameter->getUnionType()->isType(MixedType::instance(false))
                ) {
                    continue;
                }

                // TODO: check variadic.

                // Its not OK to have a more relaxed type on an
                // overriding method
                //
                // https://3v4l.org/XTm3P
                if ($parameter->getUnionType()->isEmpty()) {
                    $signatures_match = false;
                    break;
                }

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
                $method->getNumberOfRequiredRealParameters(),
                $o_method->getNumberOfRequiredRealParameters()
            );
            return;
        } else if ($method->getNumberOfRealParameters()
            < $o_method->getNumberOfRealParameters()
        ) {
            self::emitSignatureRealMismatchIssue(
                $code_base,
                $method,
                $o_method,
                Issue::ParamSignatureRealMismatchTooFewParameters,
                Issue::ParamSignatureRealMismatchTooFewParametersInternal,
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
                    (string)$return_union_type,
                    (string)$o_return_union_type
                );
            }
        }
        if ($is_possibly_compatible) {
            if (Config::get()->inherit_phpdoc_types) {
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
    private static function emitSignatureRealMismatchIssue(CodeBase $code_base, Method $method, Method $o_method, string $issue_type, string $internal_issue_type, ...$args) {
        if ($method->hasSuppressIssue($internal_issue_type)) {
            return;
        }
        if ($o_method->isPHPInternal()) {
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
}
