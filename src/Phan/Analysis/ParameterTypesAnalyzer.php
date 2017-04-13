<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\Type\TemplateType;
use Phan\Plugin\PluginImplementation;

class ParameterTypesAnalyzer extends PluginImplementation
{

    /**
     * @param CodeBase $code_base
     * The code base in which the function exists
     *
     * @param Func $function
     * A function being analyzed
     *
     * @return void
     */
    public function analyzeFunction(
        CodeBase $code_base,
        Func $function
    ) {
        $this->analyzeParameterTypes($code_base, $function);
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the method exists
     *
     * @param Method $method
     * A method being analyzed
     *
     * @return void
     */
    public function analyzeMethod(
        CodeBase $code_base,
        Method $method
    ) {
        $this->analyzeParameterTypes($code_base, $method);
    }

    /**
     * Check method parameters to make sure they're valid
     *
     * @return void
     */
    private function analyzeParameterTypes(
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
    }

}
