<?php declare(strict_types=1);
namespace Phan\Plugin;

use Phan\AST\Visitor\Element;

/**
 * Maps an \ast\Node->kind value to the corresponding closures.
 */
class ClosuresForKind {
    /**
     * @var \Closure[][]
     */
    private $closures = [];

    public function __construct() {}

    public function record(int $kind, \Closure $c)
    {
        \assert(\array_key_exists($kind, Element::VISIT_LOOKUP_TABLE));
        if (!isset($this->closures[$kind])) {
            $this->closures[$kind] = [];
        }
        $this->closures[$kind][] = $c;
    }

    public function recordForKinds(array $kinds, \Closure $c)
    {
        foreach ($kinds as $kind) {
            $this->record($kind, $c);
        }
    }

    public function recordForAllKinds(\Closure $c)
    {
        $this->recordForKinds(array_keys(Element::VISIT_LOOKUP_TABLE), $c);
    }

    /**
     * @return \Closure[]
     */
    public function getFlattenedClosures(\Closure $flattener)
    {
        ksort($this->closures);
        $merged_closures = [];
        foreach ($this->closures as $kind => $closure_list) {
            assert(\count($closure_list) > 0);
            if (\count($closure_list) === 1) {
                $merged_closures[$kind] = $closure_list[0];
            } else {
                $merged_closures[$kind] = $flattener($closure_list);
            }
        }
        return $merged_closures;
    }
}
