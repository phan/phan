<?php

namespace CondNS {
    function ns_safe(int|string $x): bool
    {
        return \is_string($x);
    }

    function test_same_namespace(int|string $x): void
    {
        if (ns_safe($x)) {
            '@phan-debug-var $x';
        }
        if (\CondNS\ns_safe($x)) {
            '@phan-debug-var $x';
        }
        // Falls back to the global function \is_safe()
        if (is_safe($x)) {
            '@phan-debug-var $x';
        }
    }
}

namespace CondOther {
    use function CondNS\ns_safe as ok;

    function test_alias(int|string $x): void
    {
        if (ok($x)) {
            '@phan-debug-var $x';
        } else {
            '@phan-debug-var $x';
        }
    }
}
