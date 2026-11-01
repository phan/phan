<?php
class V017Base
{
    /** @throws InvalidArgumentException */
    public function risky(): void {}
    /** @throws RuntimeException */
    public function riskier(): int { return 1; }
}
