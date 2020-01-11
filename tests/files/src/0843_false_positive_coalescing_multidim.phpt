<?php
class Example {
    const X = [
        'foo' => [
            'a' => 'b',
        ],
    ];

    public function main(string $key) : ?string {
        // Should not emit PhanCoalescingNeverNull.
        return self::X[$key]['a'] ?? null;
    }
}
