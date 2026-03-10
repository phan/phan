<?php

// Test that qualified function calls don't produce false positive casing warnings
// via incorrect global namespace fallback.
//
// PHP's name resolution for qualified (but not fully-qualified) function calls:
//   From namespace \OtherNs, calling QualNs\func() resolves to \OtherNs\QualNs\func().
//   If that doesn't exist, PHP does NOT fall back to \QualNs\func() — it's an error.
//   (Global fallback only applies to unqualified function calls.)

namespace QualNs319 {
    function myFunc319(): void {}
}

namespace OtherNs319 {
    function test_qualified_no_false_positive(): void {
        // Qualified call: PHP resolves this to \OtherNs319\QualNs319\MYFUNC319(),
        // which doesn't exist. The plugin should NOT fall back to \QualNs319\myFunc319()
        // and report a false positive casing mismatch.
        QualNs319\MYFUNC319();
    }

    function test_fq_should_still_warn(): void {
        // Fully-qualified call: this correctly resolves to \QualNs319\myFunc319()
        // and should warn about the casing mismatch.
        \QualNs319\MYFUNC319();
    }
}
