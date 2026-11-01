<?php

declare(strict_types=1);

namespace Phan\Tests\Plugin;

use Phan\Config;
use Phan\Plugin\ConfigPluginSet;
use Phan\Tests\AbstractPhanFileTestBase;

/**
 * Integration tests for StrictLiteralComparisonPlugin.
 */
final class StrictLiteralComparisonPluginTest extends AbstractPhanFileTestBase
{
    // Relative to the project root, like the directories in tests/bootstrap.php, so that dumped expectations use `%s`.
    private const TEST_DIRECTORY = './tests/strict_literal_comparison_plugin_test';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        Config::setValue('plugins', ['StrictLiteralComparisonPlugin']);
        ConfigPluginSet::reset();  // @phan-suppress-current-line PhanAccessMethodInternal
    }

    public static function getTestFiles(): array
    {
        return self::scanSourceFilesDir(
            self::TEST_DIRECTORY . '/src',
            self::TEST_DIRECTORY . '/expected'
        );
    }
}
