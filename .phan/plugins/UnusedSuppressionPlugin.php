<?php declare(strict_types=1);

use Phan\AST\AnalysisVisitor;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\AddressableElement;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Func;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;
use Phan\PluginV2;
use Phan\PluginV2\AnalyzeClassCapability;
use Phan\PluginV2\AnalyzeFunctionCapability;
use Phan\PluginV2\AnalyzePropertyCapability;
use Phan\PluginV2\AnalyzeMethodCapability;
use Phan\PluginV2\FinalizeProcessCapability;
use ast\Node;

/**
 * Check for unused (at)suppress annotations.
 *
 * NOTE! This plugin only produces correct results when Phan
 *       is run on a single processor (via the `-j1` flag).
 */
class UnusedSuppressionPlugin extends PluginV2 implements
    AnalyzeClassCapability,
    AnalyzeFunctionCapability,
    AnalyzeMethodCapability,
    AnalyzePropertyCapability,
    FinalizeProcessCapability
{

    /**
     * @var AddressableElement[] - Analysis is postponed until finalizeProcess.
     * Issues may have been emitted after `$this->analyze*()` were called,
     * which is why those methods postpone the check until analysis is finished.
     *
     * Also, looping over all elements again would be slow.
     *
     * These are currently unique, even when quick_mode is false.
     */
    private $elements_for_postponed_analysis = [];

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

        if (\array_key_exists('UnusedSuppression', $suppress_issue_list)) {
            // The element's doc comment is suppressing everything emitted by this plugin.
            return;
        }

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
     * @param CodeBase $unused_code_base
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
        CodeBase $unused_code_base,
        Clazz $class
    ) {
        $this->elements_for_postponed_analysis[] = $class;
    }

    /**
     * @param CodeBase $unused_code_base
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
        CodeBase $unused_code_base,
        Method $method
    ) {

        // Ignore methods inherited by subclasses
        if ($method->getFQSEN() !== $method->getDefiningFQSEN()) {
            return;
        }

        $this->elements_for_postponed_analysis[] = $method;
    }

    /**
     * @param CodeBase $unused_code_base
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
        CodeBase $unused_code_base,
        Func $function
    ) {
        $this->elements_for_postponed_analysis[] = $function;
    }

    /**
     * @param CodeBase $unused_code_base
     * The code base in which the function exists
     *
     * @param Property $property
     * A property being analyzed
     *
     * @return void
     *
     * @override
     */
    public function analyzeProperty(
        CodeBase $unused_code_base,
        Property $property
    ) {
        $this->elements_for_postponed_analysis[] = $property;
    }

    /**
     * NOTE! This plugin only produces correct results when Phan
     *       is run on a single processor (via the `-j1` flag).
     *       Putting this hook in finalizeProcess() just minimizes the incorrect result counts.
     * @override
     */
    public function finalizeProcess(CodeBase $code_base)
    {
        foreach ($this->elements_for_postponed_analysis as $element) {
            $this->analyzeAddressableElement($code_base, $element);
        }
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which its defined.
return new UnusedSuppressionPlugin;
