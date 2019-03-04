<?php declare(strict_types=1);

namespace Phan\Tests\Plugin\Internal;

use Phan\CodeBase;
use Phan\Issue;
use Phan\IssueInstance;
use Phan\Plugin\Internal\IssueFixingPlugin\IssueFixer;
use Phan\Tests\BaseTest;
use Phan\Tests\CodeBaseAwareTestInterface;

/**
 * Unit tests of fixes to issues
 */
final class IssueFixingPluginTest extends BaseTest implements CodeBaseAwareTestInterface
{
    /** @var CodeBase|null The code base within which this unit test is operating */
    private $code_base = null;

    public function setCodeBase(CodeBase $code_base = null)
    {
        $this->code_base = $code_base;
    }

    const FILE = 'fix_test.php';

    /**
     * @param IssueInstance[] $instances
     * @dataProvider computeAndApplyFixesProvider
     */
    public function testComputeAndApplyFixes(string $expected_contents, string $original_contents, array $instances)
    {
        $fixers = IssueFixer::computeFixersForInstances($instances);
        //var_export($instances);

        $this->assertCount(1, $fixers);
        $fixers_for_file = $fixers[self::FILE];
        // echo "Going to apply to \n$original_contents\n";
        // @phan-suppress-next-line PhanPossiblyNullTypeArgument
        $new_contents = IssueFixer::computeNewContentForFixers($this->code_base, self::FILE, $original_contents, $fixers_for_file);
        $this->assertSame($expected_contents, $new_contents, 'unexpected contents after applying fixes');
    }

    /**
     * @return array<int,array{0:string,1:string,2:array<int,IssueInstance>}>
     */
    public function computeAndApplyFixesProvider() : array
    {
        /**
         * @param int|string ...$args
         */
        $make = static function (string $type, int $line, ...$args) : IssueInstance {
            return new IssueInstance(Issue::fromType($type), self::FILE, $line, $args);
        };
        return [
            [
                <<<'EOT'
<?php
namespace test;
use function strlen;
echo "Hello, world!";
echo strlen($argv[0]);
EOT
                ,
                <<<'EOT'
<?php
namespace test;
use ast\Node;
use function foo;
use function strlen;
use const ast\AST_VERSION;
echo "Hello, world!";
echo strlen($argv[0]);
EOT
                ,
                [
                    $make(Issue::UnreferencedUseNormal, 3, 'Node', '\ast\Node'),
                    $make(Issue::UnreferencedUseFunction, 4, 'foo', '\foo'),
                    $make(Issue::UnreferencedUseConstant, 6, 'AST_VERSION', '\ast\AST_VERSION'),
                ],
            ],
        ];
    }
}
