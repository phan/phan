<?php

class AsymmetricVisibilityRestrictiveness
{
    public function __construct(
        public public(set) int $publicReadPublicSet = 0,
        public protected(set) int $publicReadProtectedSet = 0,
        public private(set) int $publicReadPrivateSet = 0,
        protected public(set) int $protectedReadPublicSet = 0,
        protected protected(set) int $protectedReadProtectedSet = 0,
        protected private(set) int $protectedReadPrivateSet = 0,
        private public(set) int $privateReadPublicSet = 0,
        private protected(set) int $privateReadProtectedSet = 0,
        private private(set) int $privateReadPrivateSet = 0
    ) {
    }

    public function useAllProperties(): int
    {
        return $this->publicReadPublicSet
            + $this->publicReadProtectedSet
            + $this->publicReadPrivateSet
            + $this->protectedReadPublicSet
            + $this->protectedReadProtectedSet
            + $this->protectedReadPrivateSet
            + $this->privateReadPublicSet
            + $this->privateReadProtectedSet
            + $this->privateReadPrivateSet;
    }
}

$r = (new AsymmetricVisibilityRestrictiveness())->useAllProperties();

