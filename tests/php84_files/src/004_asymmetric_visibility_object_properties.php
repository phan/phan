<?php
// @phan-file-suppress PhanUnusedPublicNoOverrideMethodParameter, PhanWriteOnlyProtectedProperty

class AsymmetricVisibilityBase
{
    public function __construct(
        public int $publicReadPublicSet = 0,
        protected(set) int $publicReadProtectedSet = 0,
        private(set) int $publicReadPrivateSet = 0,
        protected private(set) int $protectedReadPrivateSet = 0,
    ) {
    }

    public function setPublicReadProtectedSet(int $value): void
    {
        $this->publicReadProtectedSet = $value;
    }

    public function setPublicReadPrivateSet(int $value): void
    {
        $this->publicReadPrivateSet = $value;
    }
}

class AsymmetricVisibilityChild extends AsymmetricVisibilityBase
{
    public function setPublicReadPrivateSetOverride(int $value): void
    {
        $this->publicReadPrivateSet = $value;
    }

    public function setPublicReadProtectedSetOverride(int $value): void
    {
        $this->publicReadProtectedSet = $value;
    }
}

$t1 = new AsymmetricVisibilityBase();
$t1->publicReadPublicSet = 1;
$r = $t1->publicReadPublicSet;

$t1->publicReadProtectedSet = 2;
$t1->setPublicReadProtectedSet(2);
$r = $t1->publicReadProtectedSet;

$t1->publicReadPrivateSet = 3;
$t1->setPublicReadPrivateSet(3);
$r = $t1->publicReadPrivateSet;

$t1->protectedReadPrivateSet = 4;
$r = $t1->protectedReadPrivateSet;

$t2 = new AsymmetricVisibilityChild();
$t2->setPublicReadProtectedSet(21);
$r = $t2->publicReadProtectedSet;

$t2->setPublicReadPrivateSet(32);
$r = $t2->publicReadPrivateSet;

$t2->setPublicReadPrivateSetOverride(33);

$t2->setPublicReadProtectedSetOverride(41);
