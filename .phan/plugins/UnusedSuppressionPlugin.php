<?php declare(strict_types=1);

use Phan\Analysis\ClassAnalyzer;
use Phan\Analysis\FunctionAnalyzer;
use Phan\Analysis\MethodAnalyzer;
use Phan\AST\AnalysisVisitor;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Func;
use Phan\Language\Element\Method;
use Phan\Language\Element\TypedElement;
use Phan\Plugin;
use Phan\PluginIssue;
use Phan\Plugin\PluginImplementation;
use ast\Node;

/**
 * Check for unused `@suppress` annotations.
 *
 * NOTE! This plugin only produces correct results when Phan
 *       is run on a single processor (via the `-j1` flag).
 */
class UnusedSuppressionPlugin implements ClassAnalyzer, FunctionAnalyzer, MethodAnalyzer {
    use PluginIssue;

    /**
     * @param CodeBase $code_base
     * The code base in which the function exists
     *
     * @param TypedElement $element
     * Any element such as function, method, class
     *
     * @return void
     */
    private function analyzeTypedElement(
        CodeBase $code_base,
        TypedElement $element
    ) {
        // Get the set of suppressed issues on the element
        $suppress_issue_list =
            $element->getSuppressIssueList();

        // Check to see if any are unused
        foreach ($suppress_issue_list as $issue_type => $use_count) {
            if (0 === $use_count) {
                $this->emitPluginIssue(
                    $code_base,
                    $element->getContext(),
                    'UnusedSuppression',
                    "Element {$element->getFQSEN()} suppresses issue $issue_type but does not use it"
                );
            }
        }
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the class exists
     *
     * @param Clazz $class
     * A class being analyzed
     *
     * @return void
     */
    public function analyzeClass(
        CodeBase $code_base,
        Clazz $class
    ) {
        $this->analyzeTypedElement($code_base, $class);
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

        // Ignore methods inherited by subclasses
        if ($method->getFQSEN() !== $method->getDefiningFQSEN()) {
            return;
        }

        $this->analyzeTypedElement($code_base, $method);
    }

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
        $this->analyzeTypedElement($code_base, $function);
    }

}

// Every plugin needs to return an instance of itself at the
// end of the file in which its defined.
return UnusedSuppressionPlugin::class;
