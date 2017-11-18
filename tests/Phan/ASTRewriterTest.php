<?php declare(strict_types=1);

namespace Phan\Tests;

use Phan\AST\ASTSimplifier;
use Phan\Config;
use Phan\Debug;

class ASTRewriterTest extends AbstractPhanFileTest
{
    /**
     * @suppress PhanUndeclaredConstant
     */
    public function getTestFiles()
    {
        /** @return string[] - Original file and expected file */
        return array_map(function (array $values) : array {
            assert(count($values[0]) === 1, 'expected only one source file');
            return [$values[0][0], $values[1]];
        }, $this->scanSourceFilesDir(AST_TEST_FILE_DIR, AST_EXPECTED_DIR));
    }

    /**
     * This reads all files in `tests/files/src`, runs
     * the analyzer on each and compares the output
     * to the ASTs of the counterparts in
     * `tests/files/expected`
     *
     * @param string[] $test_file_list @phan-unused-param
     * @param string $expected_file_path
     * @param ?string $config_file_path @phan-unused-param
     * @dataProvider getTestFiles
     * @override
     */
    public function testFiles($test_file_list, $expected_file_path, $config_file_path = null)
    {
        // Read the expected output
        $original_src =
            file_get_contents($expected_file_path);
        $expected_src =
            file_get_contents($expected_file_path);
        $this->assertNotEquals(false, $original_src);
        $this->assertNotEquals(false, $expected_src);

        $ast_version = Config::AST_VERSION;
        $expected = \ast\parse_code($expected_src, $ast_version);
        $beforeTransform = \ast\parse_code($original_src, $ast_version);

        // We use identical files for testing ASTs which aren't expected to change.
        if (trim($expected_src) !== trim($original_src)) {
            $this->assertNotEquals(Debug::astDump($expected), Debug::astDump($beforeTransform), 'Expected the input asts to be different');
        }
        $actual = ASTSimplifier::applyStatic($beforeTransform);
        $this->assertInstanceOf('AST\Node', $actual, 'should return an AST');
        $this->assertSame(\ast\AST_STMT_LIST, $actual->kind, 'should return an AST of kind AST_STMT_LIST');
        $actualRepr = Debug::astDump($actual);
        $expectedRepr = Debug::astDump($expected);
        $this->assertEquals($expectedRepr, $actualRepr, 'Expected the AST representation to be the same as the expected source\'s after transformations');
    }
}
