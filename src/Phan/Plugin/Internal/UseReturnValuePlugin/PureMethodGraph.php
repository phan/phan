<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal\UseReturnValuePlugin;

use Phan\CodeBase;
use Phan\Language\Element\FunctionInterface;

/**
 * Data structure used to recursively check if a function or method is pure.
 *
 * - If an edge points to a function not in the graph, that function is not pure.
 *   (and so are the functions pointing to it)
 *
 * - If all outgoing edges point to pure functions, then the function is pure.
 *
 * - If a key is found in the graph, the purity of the function is being determined inside this class.
 */
class PureMethodGraph
{
    /** @var CodeBase used to warn */
    private $code_base;

    /**
     * Maps lowercase function keys to the lowercase fqsen keys to the corresponding functions.
     * @var array<string,array<string,FunctionInterface>>
     */
    private $dependencies = [];

    /**
     * Maps lowercase function keys to the corresponding function or method.
     * This is used to mark any functions that were identified as pure.
     * @var array<string,FunctionInterface>
     */
    private $functions = [];

    /**
     * Contains function nodes to depend on nodes that weren't pure.
     * @var list<String>
     */
    private $to_process = [];

    public function __construct(CodeBase $code_base)
    {
        $this->code_base = $code_base;
    }

    /**
     * Record that $fqsen_key with the corresponding function $function depends on $dependencies
     * to recursively determine if it is pure.
     *
     * @param array<string, FunctionInterface> $dependencies
     */
    public function recordPotentialPureFunction(string $fqsen_key, FunctionInterface $function, array $dependencies): void
    {
        if ($dependencies) {
            $this->dependencies[$fqsen_key] = $dependencies;
        } else {
            $this->recordPureFunction($function);
        }

        $this->functions[$fqsen_key] = $function;
    }

    private function recordPureFunction(FunctionInterface $function): void
    {
        if ($function->getUnionType()->isNull()) {
            PureMethodInferrer::warnNoopVoid($this->code_base, $function);
        }
        $function->setIsPure();
    }

    private function handleImpureFunction(string $key): void
    {
        if (!isset($this->dependencies[$key])) {
            return;
        }
        unset($this->dependencies[$key]);
        unset($this->functions[$key]);
        $this->to_process[] = $key;
    }

    /**
     * Recursively mark nodes as pure.
     *
     * This should be done exactly once after adding all functions and methods.
     */
    public function recursivelyMarkNodesAsPure(): void
    {
        // Go through the graph. Any nodes that are impure would either never be added to $this->functions,
        // or be removed in handleImpureFunction.
        $reverse_edges = [];
        foreach ($this->dependencies as $source_key => $target_map) {
            foreach ($target_map as $target_key => $target) {
                if ($target->isPure()) {
                    unset($this->dependencies[$target_key]);
                    continue;
                }
                if (!isset($this->functions[$target_key])) {
                    $this->handleImpureFunction($source_key);
                    continue;
                }
                if (!isset($reverse_edges[$target_key])) {
                    $reverse_edges[$target_key] = [$source_key];
                }
                $reverse_edges[$target_key][] = $source_key;
            }
        }
        while ($this->to_process) {
            $impure_key = \array_pop($this->to_process);
            foreach ($reverse_edges[$impure_key] ?? [] as $depending_on_impure_key) {
                $this->handleImpureFunction($depending_on_impure_key);
            }
        }
        // The remaining nodes must depend only on other functions that weren't recursively identified as impure.
        foreach ($this->functions as $function) {
            $this->recordPureFunction($function);
        }
    }
}
