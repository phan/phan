<?php declare(strict_types=1);

namespace Phan\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Any common initialization or configuration should go here
 * (E.g. this changes https://phpunit.de/manual/current/en/fixtures.html#fixtures.global-state for some classes)
 */
abstract class BaseTest extends TestCase
{
    // Needed to prevent phpunit from backing up these private static variables.
    // See https://phpunit.de/manual/current/en/fixtures.html#fixtures.global-state
    protected $backupStaticAttributesBlacklist = [
        'Phan\Language\Type' => [
            'canonical_object_map',
            'internal_fn_cache',
        ],
    ];
}
