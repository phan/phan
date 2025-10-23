<?php

class FinalPromotionExample {
    public function __construct(
        public final string $name,
        protected final int $count = 0,
        private final bool $enabled = true,
    ) {}
}
