<?php

// Test: self::$prop ?? null should not warn about CoalescingNeverUndefined
// because static properties can be unset or null
class CoalesceTest5917 {
    /** @var string|null */
    private static $config = null;

    public static function getConfig(): ?string {
        // This should NOT emit PhanCoalescingNeverUndefined
        return self::$config ?? null;
    }

    /** @var int */
    private static int $count = 0;

    public static function getCount(): int {
        // This SHOULD still emit PhanCoalescingNeverNull because the
        // real type is int (non-null), making ?? redundant
        return self::$count ?? 0;
    }
}
