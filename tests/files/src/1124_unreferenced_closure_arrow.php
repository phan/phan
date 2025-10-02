<?php

// Regression test for https://github.com/phan/phan/issues/5040

function takesClosure(Closure $clos) {
    echo $clos();
}

takesClosure( static fn (): int => 42 ); // Should not be reported as unreferenced
