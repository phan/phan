<?php
class V015Base
{
    private function secret(): int { return 1; }
    public static function create(): static { return new static(); }
    protected static function helper(string $s): string { return $s; }
    public function usesSecret(): int { return $this->secret(); }
}
