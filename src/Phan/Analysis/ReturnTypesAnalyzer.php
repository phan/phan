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
        $real_return_type = $method->getRealReturnType();
        $phpdoc_return_type = $method->getUnionType();
        $context = $method->getContext();
        if (Config::get()->check_docblock_signature_return_type_match && !$real_return_type->isEmpty() && !$phpdoc_return_type->isEmpty()) {
            $resolved_real_return_type = $real_return_type->withStaticResolvedInContext($context);
            foreach ($phpdoc_return_type->getTypeSet() as $phpdoc_type) {
                // Make sure that the commented type is a narrowed
                // or equivalent form of the syntax-level declared
                // return type.
                if (!$phpdoc_type->isExclusivelyNarrowedFormOrEquivalentTo(
                        $resolved_real_return_type,
                        $context,
                        $code_base
                    )
                ) {
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
            }
        }
    }
}
