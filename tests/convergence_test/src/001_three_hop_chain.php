<?php

/**
 * Test: 3-hop call chain type inference.
 * Files are named so App is analyzed first alphabetically,
 * but depends on MiddleLayer which depends on Provider.
 *
 * With --analyze-until-convergence, the type of $obj should
 * be inferred as \ConvergenceService through the full chain.
 */

class ConvergenceService {
    public function doSomething(): void {}
}

class ConvergenceProvider {
    function provide() {
        return new ConvergenceService();
    }
}

class ConvergenceMiddle {
    function get() {
        return (new ConvergenceProvider())->provide();
    }
}

class ConvergenceApp {
    function run(): void {
        $obj = (new ConvergenceMiddle())->get();
        // Without convergence, $obj would be unknown and this would not warn.
        // With convergence, $obj is ConvergenceService and this is a type error.
        $this->expectInt($obj);
    }

    function expectInt(int $v): void {
        var_dump($v);
    }
}
