<?php

/** Example for issue 4685 where array appends may still leave the array empty. */
class Issue4685Example
{
    /** @var array<int,\stdClass> */
    private array $changes = [];

    /**
     * Adds items to the pending list when available.
     *
     * @param array<int,\stdClass> $items
     */
    private function add(array $items): void
    {
        if ($items !== []) {
            foreach ($items as $item) {
                $this->changes[] = $item;
            }
        }
    }

    /**
     * Processes items and conditionally closes the list.
     *
     * @param array<int,\stdClass> $items
     */
    public function output(array $items): void
    {
        $this->add($items);
        $inList = false;
        foreach ($this->changes as $_) {
            if (!$inList) {
                $inList = true;
            }
        }
        if ($inList) {
            return;
        }
    }
}

/**
 * Forward changes along for analysis.
 *
 * @param array<int,\stdClass> $items
 */
function issue4685(Issue4685Example $example, array $items): void
{
    $example->output($items);
}
