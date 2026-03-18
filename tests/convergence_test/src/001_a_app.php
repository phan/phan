<?php

/**
 * Analyzed first (alphabetically), but depends on ConvergenceMiddle
 * which depends on ConvergenceProvider. Without convergence/reordering,
 * $obj would have an unknown type since the chain hasn't been resolved yet.
 */
class ConvergenceApp {
    function run(): void {
        $obj = (new ConvergenceMiddle())->get();
        $this->expectInt($obj);
    }

    function expectInt(int $v): void {
        var_dump($v);
    }
}
