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
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\IterableType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\TemplateType;
use Phan\Language\UnionType;

class ReturnTypesAnalyzer
{

    /**
     * Check method return types (phpdoc and real) to make sure they're valid
     *
     * @return void
     */
    public static function analyzeReturnTypes(
        CodeBase $code_base,
        FunctionInterface $method
    ) {
        $return_type = $method->getUnionType();
        $real_return_type = $method->getRealReturnType();
        $phpdoc_return_type = $method->getPHPDocReturnType();
        // TODO: use method->getPHPDocUnionType() to check compatibility, like analyzeParameterTypesDocblockSignaturesMatch

        // Look at each parameter to make sure their types
        // are valid

        // Look at each type in the function's return union type
        foreach ($return_type->getTypeSet() as $outer_type) {
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
                        Issue::UndeclaredTypeReturnType,
                        $method->getFileRef()->getLineNumberStart(),
                        $method->getName(),
                        (string)$outer_type
                    );
                }
            }
        }
        if (Config::getValue('check_docblock_signature_return_type_match') && !$real_return_type->isEmpty() && ($phpdoc_return_type instanceof UnionType) && !$phpdoc_return_type->isEmpty()) {
            $context = $method->getContext();
            $resolved_real_return_type = $real_return_type->withStaticResolvedInContext($context);
            foreach ($phpdoc_return_type->getTypeSet() as $phpdoc_type) {
                $is_exclusively_narrowed = $phpdoc_type->isExclusivelyNarrowedFormOrEquivalentTo(
                    $resolved_real_return_type,
                    $context,
                    $code_base
                );
                // Make sure that the commented type is a narrowed
                // or equivalent form of the syntax-level declared
                // return type.
                if (!$is_exclusively_narrowed) {
                    if (!$method->hasSuppressIssue(Issue::TypeMismatchDeclaredReturn)) {
                        Issue::maybeEmit(
                            $code_base,
                            $context,
                            Issue::TypeMismatchDeclaredReturn,
                            $context->getLineNumberStart(),
                            $method->getName(),
                            $phpdoc_type->__toString(),
                            $real_return_type->__toString()
                        );
                    }
                }
                if ($is_exclusively_narrowed && Config::getValue('prefer_narrowed_phpdoc_return_type')) {
                    $normalized_phpdoc_return_type = ParameterTypesAnalyzer::normalizeNarrowedParamType($phpdoc_return_type, $real_return_type);
                    if ($normalized_phpdoc_return_type) {
                        $method->setUnionType($normalized_phpdoc_return_type);
                    } else {
                        // This check isn't urgent to fix, and is specific to nullable casting rules,
                        // so use a different issue type.
                        if (!$method->hasSuppressIssue(Issue::TypeMismatchDeclaredReturnNullable)) {
                            Issue::maybeEmit(
                                $code_base,
                                $context,
                                Issue::TypeMismatchDeclaredReturnNullable,
                                $context->getLineNumberStart(),
                                $method->getName(),
                                $phpdoc_type->__toString(),
                                $real_return_type->__toString()
                            );
                        }
                    }
                }
            }
        }
    }
}
