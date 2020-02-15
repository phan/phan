<?php

declare(strict_types=1);

namespace Phan\Tests\Plugin\Internal;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Plugin\Internal\BaselineLoadingPlugin;
use Phan\Tests\BaseTest;

/**
 * Unit tests of fixes to issues
 */
final class BaselineLoadingPluginTest extends BaseTest
{
    public function testShouldSuppressIssue(): void
    {
        $code_base = new CodeBase([], [], [], [], []);
        $plugin = new BaselineLoadingPlugin(__DIR__ . '/baseline.php.example');
        $assertShouldSuppressIssueEquals = function (bool $expected, string $file, string $issue_type) use ($code_base, $plugin): void {
            $context = (new Context())->withFile($file);
            $this->assertSame($expected, $plugin->shouldSuppressIssue($code_base, $context, $issue_type, 1, [], null));
        };
        $assertShouldSuppressIssueEquals(false, 'src/test.php.php', 'PhanUndeclaredMethod');
        $assertShouldSuppressIssueEquals(true, 'src/test.php', 'PhanUndeclaredMethod');

        // directory suppressions
        $assertShouldSuppressIssueEquals(false, 'src/test.php', 'PhanNoopConstant');
        $assertShouldSuppressIssueEquals(true, 'lib/test.php', 'PhanNoopConstant');

        $assertShouldSuppressIssueEquals(false, 'lib/test.php', 'PhanUnreferencedUseNormal');
        $assertShouldSuppressIssueEquals(true, 'src/test.php', 'PhanUnreferencedUseNormal');

        // Tolerate Windows directory separators, because directory suppressions are probably manually generated.
        $assertShouldSuppressIssueEquals(false, 'lib\\test.php', 'PhanUnreferencedUseNormal');
        $assertShouldSuppressIssueEquals(true, 'src\\test.php', 'PhanUnreferencedUseNormal');

        $assertShouldSuppressIssueEquals(true, 'lib/test.php', 'PhanUndeclaredProperty');
        $assertShouldSuppressIssueEquals(true, 'index.php', 'PhanUndeclaredProperty');
        $assertShouldSuppressIssueEquals(false, '../Other/test.php', 'PhanUndeclaredProperty');
    }
}
