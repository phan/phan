<?php declare(strict_types=1);
namespace Phan\Analysis;

use \Phan\CodeBase;
use \Phan\Config;
use \Phan\Issue;
use \Phan\Language\Element\FunctionInterface;
use \Phan\Language\Element\Method;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedFunctionName;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;
use \Phan\Language\Type\MixedType;

class ParameterTypesAnalyzer
{

    /**
     * Check method parameters to make sure they're valid
     *
     * @return null
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


                // Otherwise, make sure the class exists
                $type_fqsen = $type->asFQSEN();
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
        $class = $method->getDefiningClass($code_base);
        $class->hydrate($code_base);

        // Check to see if the method is an override
        // $method->analyzeOverride($code_base);

        // Make sure we're actually overriding something
        if (!$method->getIsOverride()) {
            return;
        }

        // Dont' worry about signatures lining up on
        // constructors. We just want to make sure that
        // calling a method on a subclass won't cause
        // a runtime error. We usually know what we're
        // constructing at instantiation time, so there
        // is less of a risk.
        if ($method->getName() == '__construct') {
            return;
        }

        // Get the method that is being overridden
        $o_method = $method->getOverriddenMethod($code_base);

        // Get the class that the overridden method lives on
        $o_class = $o_method->getDefiningClass($code_base);

        // PHP doesn't complain about signature mismatches
        // with traits, so neither shall we
        if ($o_class->isTrait()) {
            return;
        }

        // Get the parameters for that method
        $o_parameter_list = $o_method->getParameterList();

        // Determine if the signatures match up
        $signatures_match = true;

        // Make sure the count of parameters matches
        if ($method->getNumberOfRequiredParameters()
            > $o_method->getNumberOfParameters()
        ) {
            $signatures_match = false;
        } else if ($method->getNumberOfParameters()
            < $o_method->getNumberOfRequiredParameters()
        ) {
            $signatures_match = false;

        // If parameter counts match, check their types
        } else {
            foreach($method->getParameterList() as $i => $parameter) {

                if (!isset($o_parameter_list[$i])) {
                    continue;
                }

                $o_parameter = $o_parameter_list[$i];

                // A stricter type on an overriding method is cool
                if ($o_parameter->getUnionType()->isEmpty()
                    || $o_parameter->getUnionType()->isType(MixedType::instance())
                ) {
                    continue;
                }

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

        // Return types should be mappable
        if (!$o_method->getUnionType()->isEmpty()) {

            if (!$method->getUnionType()->asExpandedTypes($code_base)->canCastToUnionType(
                $o_method->getUnionType()
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

        if (!$signatures_match) {
            if ($o_method->isInternal()) {
                Issue::maybeEmit(
                    $code_base,
                    $method->getContext(),
                    Issue::ParamSignatureMismatchInternal,
                    $method->getFileRef()->getLineNumberStart(),
                    $method,
                    $o_method
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
                    $o_method->getFileRef()->getLineNumberStart()
                );
            }
        }

        // Access must be compatible
        if ($o_method->isProtected() && $method->isPrivate()
            || $o_method->isPublic() && !$method->isPublic()
        ) {
            if ($o_method->isInternal()) {
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

}
