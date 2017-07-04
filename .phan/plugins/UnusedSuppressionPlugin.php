<?php declare(strict_types=1);

use Phan\AST\AnalysisVisitor;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Func;
use Phan\Language\Element\Method;
use Phan\Language\Element\AddressableElement;
use Phan\PluginV2;
use Phan\PluginV2\AnalyzeClassCapability;
use Phan\PluginV2\AnalyzeFunctionCapability;
use Phan\PluginV2\AnalyzeMethodCapability;
use ast\Node;

/**
 * Check for unused `@suppress` annotations.
 *
 * NOTE! This plugin only produces correct results when Phan
 *       is run on a single processor (via the `-j1` flag).
 */
class UnusedSuppressionPlugin extends PluginV2 implements
    AnalyzeClassCapability,
    AnalyzeFunctionCapability,
    AnalyzeMethodCapability {

    /**
     * @param CodeBase $code_base
     * The code base in which the function exists
     *
     * @param AddressableElement $element
     * Any element such as function, method, class
     * (which has an FQSEN)
     *
     * @return void
     */
    private function analyzeAddressableElement(
        CodeBase $code_base,
        AddressableElement $element
    ) {
        // Get the set of suppressed issues on the element
        $suppress_issue_list =
            $element->getSuppressIssueList();

        // Check to see if any are unused
        foreach ($suppress_issue_list as $issue_type => $use_count) {
            if (0 === $use_count) {
                $this->emitIssue(
                    $code_base,
                    $element->getContext(),
                    'UnusedSuppression',
                    "Element {FUNCTIONLIKE} suppresses issue {ISSUETYPE} but does not use it",
                    [(string)$element->getFQSEN(), $issue_type]
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
     *
     * @override
     */
    public function analyzeClass(
        CodeBase $code_base,
        Clazz $class
    ) {
        $this->analyzeAddressableElement($code_base, $class);
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the method exists
     *
     * @param Method $method
     * A method being analyzed
     *
     * @return void
     *
     * @override
     */
    public function analyzeMethod(
        CodeBase $code_base,
        Method $method
    ) {

        // Ignore methods inherited by subclasses
        if ($method->getFQSEN() !== $method->getDefiningFQSEN()) {
            return;
        }

        $this->analyzeAddressableElement($code_base, $method);
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the function exists
     *
     * @param Func $function
     * A function being analyzed
     *
     * @return void
     *
     * @override
     */
    public function analyzeFunction(
        CodeBase $code_base,
        Func $function
    ) {
        $this->analyzeAddressableElement($code_base, $function);
    }

}

// Every plugin needs to return an instance of itself at the
// end of the file in which its defined.
return new UnusedSuppressionPlugin;
