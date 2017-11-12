<?php declare(strict_types=1);
namespace Phan\Plugin;

use Phan\AST\Visitor\Element;

/**
 * Tracks the closures that need to be executed for the set of possible \ast\Node->kind values.
 * Once the plugin set is done adding closures, this will return an array
 * mapping the node kind to a single Closure to execute
 * (which will run all of the plugins)
 *
 * - If no closures were added for a given node kind, then there will be no entry in that array.
 */
class ClosuresForKind
{
    /**
     * @var \Closure[][] Maps a node kind to a list of 1 or more (unflattened) closures to execute on nodes of that kind.
     */
    private $closures = [];

    public function __construct()
    {
    }

    /**
     * @param int $kind - A valid value of a node kind
     * @param \Closure $c
     */
    public function record(int $kind, \Closure $c)
    {
        \assert(\array_key_exists($kind, Element::VISIT_LOOKUP_TABLE));
        if (!isset($this->closures[$kind])) {
            $this->closures[$kind] = [];
        }
        $this->closures[$kind][] = $c;
    }

    /**
     * @param int[] $kinds - A list of unique values of node kinds
     * @param \Closure $c - The closure to execute on each of those kinds
     *
     * Record the fact that a Closure needs to be the given subset of values of node->kind
     */
    public function recordForKinds(array $kinds, \Closure $c)
    {
        foreach ($kinds as $kind) {
            $this->record($kind, $c);
        }
    }

    /**
     * Record the fact that a Closure needs to be executed for all possible valid values of Node->kind
     * @param \Closure $c - The closure to execute on all valid values of Node->kind
     */
    public function recordForAllKinds(\Closure $c)
    {
        $this->recordForKinds(\array_keys(Element::VISIT_LOOKUP_TABLE), $c);
    }

    /**
     * @param \Closure $flattener
     * @return \Closure[] (Maps a subset of node kinds to a closure to execute for that node kind.)
     */
    public function getFlattenedClosures(\Closure $flattener)
    {
        \ksort($this->closures);
        $merged_closures = [];
        foreach ($this->closures as $kind => $closure_list) {
            \assert(\count($closure_list) > 0);
            if (\count($closure_list) === 1) {
                // If there's exactly one closure for a given kind, then execute it directly.
                $merged_closures[$kind] = $closure_list[0];
            } else {
                // Create a closure which will execute 2 or more closures.
                $closure = $flattener($closure_list);
                \assert($closure instanceof \Closure);
                $merged_closures[$kind] = $closure;
            }
        }
        return $merged_closures;
    }
}
