<?php

declare(strict_types=1);

namespace Phan\Tests\Plugin\Internal;

use Phan\Plugin\Internal\BaselineLoadingPlugin;
use Phan\Tests\TestBase;

/**
 * Unit tests of fixes to issues
 */
final class BaselineLoadingPluginTest extends TestBase
{
    public function testShouldSuppressIssue(): void
    {
        $plugin = new BaselineLoadingPlugin(__DIR__ . '/baseline.php.example');
        $assertShouldSuppressIssueEquals = function (bool $expected, string $file, string $issue_type, string $symbol = '*') use ($plugin): void {
            // @phan-suppress-next-line PhanAccessMethodInternal
            $this->assertSame($expected, $plugin->shouldSuppressIssue($issue_type, $file, $symbol));
        };
        $assertShouldSuppressIssueEquals(false, 'src/test.php.php', 'PhanUndeclaredMethod');
        $assertShouldSuppressIssueEquals(true, 'src/test.php', 'PhanUndeclaredMethod');
        $assertShouldSuppressIssueEquals(true, 'src\\test.php', 'PhanUndeclaredMethod');

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

        $assertShouldSuppressIssueEquals(true, 'lib/symbol.php', 'PhanTypeMismatchArgument', 'Foo::bar');
        $assertShouldSuppressIssueEquals(false, 'lib/symbol.php', 'PhanTypeMismatchArgument', 'Other::baz');
        $assertShouldSuppressIssueEquals(true, 'lib/closure.php', 'PhanTypeMismatchArgument', '\closure_deadbeef');
        $assertShouldSuppressIssueEquals(true, 'lib/anon.php', 'PhanTypeMismatchArgument', '\anonymous_class_beefcafe::foo');
    }
}
