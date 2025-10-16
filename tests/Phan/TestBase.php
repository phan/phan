<?php

declare(strict_types=1);

namespace Phan\Tests;

use Phan\Config;
use PHPUnit\Framework\TestCase;

/**
 * Any common initialization or configuration should go here
 * (E.g. this changes https://phpunit.de/manual/current/en/fixtures.html#fixtures.global-state for some classes)
 */
abstract class TestBase extends TestCase
{
    /**
     * @suppress PhanAccessMethodInternal
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Need more than 1G to generate code coverage reports
        \ini_set('memory_limit', '2G');
        \chdir(\dirname(__DIR__, 2));
        Config::reset();
    }
}
