<?php
/*---------------------------------------------------------------------------------------------
 *  Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;
use PHPUnit\Framework\AssertionFailedError;

class CallbackTestListener implements TestListener {
    private $cb;
    public function __construct(Closure $cb) {
        $this->cb = $cb;
    }
    use TestListenerDefaultImplementation;
    function addFailure(Test $test, AssertionFailedError $e, float $time): void {
        ($this->cb)($test);
    }
}
