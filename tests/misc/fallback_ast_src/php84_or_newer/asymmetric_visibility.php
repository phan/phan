<?php

class PromotedAsymmetric {
    public function __construct(
        public private(set) int $propPublic,
        public protected(set) string $propProtected,
        protected private(set) array $propProtectedPrivate,
        private private(set) float $propPrivate = 0.0,
        public string $remaining = 'ok',
    ) {}
}
