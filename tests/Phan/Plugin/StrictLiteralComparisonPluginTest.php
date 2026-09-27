<?php

declare(strict_types=1);

namespace Phan\Plugin;

use Phan\Config;
use Phan\Tests\AbstractPhanFileTestBase;

/**
 * Integration tests for StrictLiteralComparisonPlugin.
 */
final class StrictLiteralComparisonPluginTest extends AbstractPhanFileTestBase
{
    private const TEST_DIRECTORY = __DIR__ . '/../../strict_literal_comparison_plugin_test';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        Config::setValue('plugins', ['StrictLiteralComparisonPlugin']);
        ConfigPluginSet::reset();
    }

    public static function getTestFiles(): array
    {
        return self::scanSourceFilesDir(
            self::TEST_DIRECTORY . '/src',
            self::TEST_DIRECTORY . '/expected'
        );
    }
}
