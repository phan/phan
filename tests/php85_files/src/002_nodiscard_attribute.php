<?php

// Test #[NoDiscard] attribute support

#[\NoDiscard]
function mustUseReturnValue(): int {
    return 42;
}

class Example {
    #[\NoDiscard]
    public function mustUseThis(): string {
        return "important";
    }

    #[\NoDiscard]
    public static function mustUseThisToo(): bool {
        return true;
    }
}

// These should emit PhanNoDiscardReturnValueIgnored warnings:
// @phan-suppress-next-line PhanPluginUseReturnValueKnown
mustUseReturnValue();  // Warning: return value ignored

$obj = new Example();
// @phan-suppress-next-line PhanPluginUseReturnValueKnown
$obj->mustUseThis();  // Warning: return value ignored

// @phan-suppress-next-line PhanPluginUseReturnValueKnown
Example::mustUseThisToo();  // Warning: return value ignored

// These should NOT emit warnings - return value is used:
$result1 = mustUseReturnValue();
echo $result1;

$result2 = $obj->mustUseThis();
if ($result2) {
    echo $result2;
}

$result3 = Example::mustUseThisToo();

// This should NOT emit warning - explicitly suppressed with (void) cast (PHP 8.5+):
(void) mustUseReturnValue();
(void) $obj->mustUseThis();
(void) Example::mustUseThisToo();
