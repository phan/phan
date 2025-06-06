<?php

declare(strict_types=1);

namespace Phan\Language;

use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use RuntimeException;

/**
 * Tracks property access to detect infinite recursion in property hooks
 */
class PropertyAccessTracker
{
    /**
     * @var array<string, int> Map of property FQSEN to access depth
     */
    private $access_stack = [];

    private const MAX_DEPTH = 10;

    /**
     * Indicates a property access has been entered (for recursion tracking)
     * @throws RuntimeException if infinite recursion is detected
     */
    public function enterPropertyAccess(FullyQualifiedPropertyName $fqsen): void
    {
        $key = (string)$fqsen;
        $this->access_stack[$key] = ($this->access_stack[$key] ?? 0) + 1;

        if ($this->access_stack[$key] > self::MAX_DEPTH) {
            throw new RuntimeException("Infinite recursion detected in property hook");
        }
    }

    /**
     * Indicates property access has been exited
     */
    public function exitPropertyAccess(FullyQualifiedPropertyName $fqsen): void
    {
        $key = (string)$fqsen;
        if (isset($this->access_stack[$key])) {
            $this->access_stack[$key]--;
            if ($this->access_stack[$key] <= 0) {
                unset($this->access_stack[$key]);
            }
        }
    }

    /**
     * Reset the access stack (e.g., when entering a new function)
     * @suppress PhanUnreferencedPublicMethod
     */
    public function reset(): void
    {
        $this->access_stack = [];
    }

    /**
     * Check if a property is currently being accessed
     * @suppress PhanUnreferencedPublicMethod
     */
    public function isPropertyBeingAccessed(FullyQualifiedPropertyName $fqsen): bool
    {
        $key = (string)$fqsen;
        return isset($this->access_stack[$key]) && $this->access_stack[$key] > 0;
    }
}
