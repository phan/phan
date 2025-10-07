<?php
// Ensure #[\Override] attribute behaviour is analyzed in PHP 8.3 mode
// @phan-file-suppress PhanUnreferencedClass, PhanUnreferencedPublicMethod, PhanEmptyPublicMethod, PhanPluginUseReturnValueNoopVoid
class OverrideBase83 {
    public function foo(): void {}
}

class OverrideChild83 extends OverrideBase83 {
    #[\Override]
    public function foo(): void {}
}

class OverrideInvalid83 {
    #[\Override]
    public function bar(): void {}
}
