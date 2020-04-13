<?php

namespace NS871;

function test_loop(int $n, array $values) {
    for ($i = 0; $i < $n; $i++) { $i++;
    }
    foreach ($values as $v) {
        if ($v > 5) {
            break;
        }
    }
}

/**
 * @phan-side-effect-free
 */
class Example {
    /** @var ?Example */
    public $next;

    public function getNext(): ?Example {
        return $this->next;
    }
}

function test_while_loop(Example $e) {
    // This loop creates no variables that are used outside of the loop
    // and the loop body has no side effects
    while ($e) {
        $e = $e->next;
    }
}

function test_do_while_loop(Example $e) {
    // This loop creates no variables that are used outside of the loop
    // and the loop body has no side effects
    do {
        $e = $e->next;
    } while ($e);
}
