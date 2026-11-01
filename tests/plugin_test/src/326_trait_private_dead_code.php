<?php
// Private elements of a trait get their own dead code issue types (#5526),
// because they can be used by classes using the trait, which a library may not contain.
trait Trait5526 {
    private const UNUSED_CONST = 1;
    private const USED_CONST = 2;
    /** @suppress PhanUnreferencedPrivateClassConstant the pre-existing issue type still suppresses the trait variant */
    private const SUPPRESSED_CONST = 3;
    private $unusedProp;
    private $readOnlyProp;
    private $writeOnlyProp;
    private $usedProp;
    /** @suppress PhanUnreferencedPrivateProperty */
    private $suppressedProp;
    /** @suppress PhanReadOnlyPrivateProperty */
    private $suppressedReadOnlyProp;
    /** @suppress PhanWriteOnlyPrivateTraitProperty */
    private $suppressedWriteOnlyProp;
    // @phan-suppress-next-line PhanUnreferencedPrivateProperty
    private $suppressedNextLineProp;
    private function unusedMethod(): void {}
    /** @suppress PhanUnreferencedPrivateMethod */
    private function suppressedMethod(): void {}
    protected function unusedProtected(): void {}
    private function usedMethod(): string {
        $this->usedProp = self::USED_CONST;
        $this->writeOnlyProp = 1;
        $this->suppressedWriteOnlyProp = 1;
        return (string)($this->usedProp . $this->readOnlyProp . $this->suppressedReadOnlyProp);
    }
}
class Uses5526 {
    use Trait5526;
    public function run(): string {
        return $this->usedMethod();
    }
}
class Plain5526 {
    private const UNUSED_CONST = 1;
    private $unusedProp;
    private $readOnlyProp;
    private $writeOnlyProp;
    private function unusedMethod(): void {}
    public function run(): string {
        $this->writeOnlyProp = 1;
        return (string)$this->readOnlyProp;
    }
}
(new Uses5526())->run();
(new Plain5526())->run();
