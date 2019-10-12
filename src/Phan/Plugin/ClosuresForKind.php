<?php declare(strict_types=1);

namespace Phan\Plugin;

use AssertionError;
use Closure;
use InvalidArgumentException;
use Phan\AST\Visitor\Element;

/**
 * Tracks the closures that need to be executed for the set of possible \ast\Node->kind values.
 * Once the plugin set is done adding closures, this will return an array
 * mapping the node kind to a single Closure to execute
 * (which will run all of the plugins)
 *
 * - If no closures were added for a given node kind, then there will be no entry in that array.
 *
 * @internal
 */
class ClosuresForKind
{
    /**
     * @var associative-array<int, non-empty-list<Closure>> Maps a node kind to a list of 1 or more (unflattened) closures to execute on nodes of that kind.
     */
    private $closures = [];

    public function __construct()
    {
    }

    /**
     * Record the fact that the resulting closure for Node kind $kind should invoke $c
     *
     * @param int $kind - A valid value of a node kind
     * @param Closure $c
     * @throws InvalidArgumentException if $kind is invalid
     */
    public function record(int $kind, Closure $c) : void
    {
        if (!\array_key_exists($kind, Element::VISIT_LOOKUP_TABLE)) {
            throw new InvalidArgumentException("Invalid node kind $kind");
        }
        if (!isset($this->closures[$kind])) {
            $this->closures[$kind] = [$c];
        } else {
            $this->closures[$kind][] = $c;
        }
    }

    /**
     * Record the fact that the resulting Closure needs to call $c for the given subset of values of node->kind
     *
     * @param list<int> $kinds - A list of unique values of node kinds
     * @param Closure $c - The closure to execute on each of those kinds
     */
    public function recordForKinds(array $kinds, Closure $c) : void
    {
        foreach ($kinds as $kind) {
            $this->record($kind, $c);
        }
    }

    /**
     * @param Closure $flattener
     * @return associative-array<int,Closure> (Maps a subset of node kinds to a closure to execute for that node kind.)
     */
    public function getFlattenedClosures(Closure $flattener) : array
    {
        \ksort($this->closures);
        $merged_closures = [];
        foreach ($this->closures as $kind => $closure_list) {
            if (\count($closure_list) === 1) {
                // If there's exactly one closure for a given kind, then execute it directly.
                $merged_closures[$kind] = $closure_list[0];
            } else {
                // Create a closure which will execute 2 or more closures.
                $closure = $flattener($closure_list);
                if (!($closure instanceof Closure)) {
                    throw new AssertionError("Expected closure flattener to return a closure for kind=$kind");
                }
                $merged_closures[$kind] = $closure;
            }
        }
        return $merged_closures;
    }
}
