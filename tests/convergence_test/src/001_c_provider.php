<?php

class ConvergenceService {
    public function doSomething(): void {}
}

class ConvergenceProvider {
    function provide() {
        return new ConvergenceService();
    }
}
