<?php

declare(strict_types=1);

namespace Phan\Tests\Plugin\Internal;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\FQSEN\FullyQualifiedGlobalStructuralElement;
use Phan\Language\Scope\GlobalScope;
use Phan\Language\Type;
use Phan\Output\Collector\BufferingCollector;
use Phan\Output\Printer\PlainTextPrinter;
use Phan\Phan;
use Phan\Plugin\ConfigPluginSet;
use Phan\Tests\TestBase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Tests for PhpDeprecationPlugin across consecutive analyses in the same process.
 */
final class PhpDeprecationPluginTest extends TestBase
{
    /** @var list<string> */
    private array $temp_files = [];

    public function setUp(): void
    {
        parent::setUp();
        Config::reset();  // @phan-suppress-current-line PhanAccessMethodInternal
        ConfigPluginSet::reset();  // @phan-suppress-current-line PhanAccessMethodInternal
        Type::clearAllMemoizations();
        FullyQualifiedGlobalStructuralElement::clearPreferredNameMap();
    }

    public function tearDown(): void
    {
        foreach ($this->temp_files as $path) {
            if (\is_file($path)) {
                \unlink($path);
            }
        }
        $this->temp_files = [];
        GlobalScope::reset();
        Config::reset();  // @phan-suppress-current-line PhanAccessMethodInternal
        ConfigPluginSet::reset();  // @phan-suppress-current-line PhanAccessMethodInternal
        Type::clearAllMemoizations();
        FullyQualifiedGlobalStructuralElement::clearPreferredNameMap();
        parent::tearDown();
    }

    private function createTempPhpFile(string $contents): string
    {
        $path = \tempnam(\sys_get_temp_dir(), 'phan_depr_');
        $this->assertIsString($path);
        $php_path = $path . '.php';
        \rename($path, $php_path);
        \file_put_contents($php_path, $contents);
        $this->temp_files[] = $php_path;
        return $php_path;
    }

    /**
     * Builds a CodeBase that omits the given function names from the running PHP's
     * internal function list, simulating a PHP build without those extensions.
     *
     * @param list<string> $omitted_functions
     */
    private static function createCodeBaseWithoutFunctions(array $omitted_functions): CodeBase
    {
        global $internal_class_name_list;
        global $internal_interface_name_list;
        global $internal_trait_name_list;
        global $internal_function_name_list;
        if (!isset($internal_class_name_list)) {
            require_once(\dirname(__DIR__, 4) . '/src/codebase.php');
        }
        $omitted = \array_flip(\array_map('strtolower', $omitted_functions));
        $filtered_functions = \array_values(\array_filter(
            $internal_function_name_list,
            static fn(string $name): bool => !isset($omitted[\strtolower($name)])
        ));
        return new CodeBase(
            $internal_class_name_list,
            $internal_interface_name_list,
            $internal_trait_name_list,
            CodeBase::getPHPInternalConstantNameList(),
            $filtered_functions
        );
    }

    /**
     * @suppress PhanThrowTypeAbsentForCall
     */
    private static function analyzeFile(CodeBase $code_base, string $file_path): string
    {
        $stream = new BufferedOutput();
        $printer = new PlainTextPrinter();
        $printer->configureOutput($stream);

        Phan::setPrinter($printer);
        Phan::setIssueCollector(new BufferingCollector());
        Phan::analyzeFileList($code_base, /** @return list<string> */ static fn(): array => [$file_path]);

        return $stream->fetch();
    }

    /**
     * Regression test for #5649: a userland declaration of a deprecated internal function
     * absent from the running PHP binary must not trigger PhanDeprecatedFunctionInternal,
     * even when another analysis already ran in the same process.
     */
    public function testUserlandPolyfillAfterEarlierAnalysis(): void
    {
        Config::setValue('target_php_version', '8.6');
        Config::setValue('minimum_target_php_version', '8.6');
        ConfigPluginSet::reset();  // @phan-suppress-current-line PhanAccessMethodInternal

        $base_code_base = self::createCodeBaseWithoutFunctions(['mhash']);

        $unrelated = $this->createTempPhpFile("<?php\n\$x = 1;\n");
        $polyfill = $this->createTempPhpFile(<<<'PHP'
<?php
function mhash(int $algo, string $data): int
{
    return $algo + strlen($data);
}

echo mhash(1, "data");
PHP
        );
        $without_polyfill = $this->createTempPhpFile("<?php\necho mhash(1, \"data\");\n");

        // Run an earlier analysis in the same process without resetting ConfigPluginSet.
        $this->assertSame('', self::analyzeFile($base_code_base->shallowClone(), $unrelated));

        // Analyzing the polyfill after another analysis must not report PhanDeprecatedFunctionInternal.
        $polyfill_output = self::analyzeFile($base_code_base->shallowClone(), $polyfill);
        $this->assertStringContainsString('PhanRedefineFunctionInternal', $polyfill_output);
        $this->assertStringNotContainsString('PhanDeprecatedFunctionInternal', $polyfill_output);

        // Calling mhash() without a userland declaration must still report PhanDeprecatedFunctionInternal.
        $direct_call_output = self::analyzeFile($base_code_base->shallowClone(), $without_polyfill);
        $this->assertStringContainsString('PhanDeprecatedFunctionInternal', $direct_call_output);
    }

    /**
     * Lazy-load hooks must be enabled during Analysis::loadMethodPlugins() so that
     * internal functions loaded by a user plugin's getAnalyzeFunctionCallClosures()
     * still receive CallableParamPlugin checks.
     */
    public function testLazyLoadHookRunsDuringLoadMethodPlugins(): void
    {
        $plugin_file = $this->createTempPhpFile(<<<'PHP'
<?php
use Phan\CodeBase;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeFunctionCallCapability;

return new class extends PluginV3 implements AnalyzeFunctionCallCapability {
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base): array
    {
        $fqsen = FullyQualifiedFunctionName::fromFullyQualifiedString('\\register_shutdown_function');
        $code_base->hasFunctionWithFQSEN($fqsen);
        return [];
    }
};
PHP
        );
        Config::setValue('plugins', [$plugin_file]);
        ConfigPluginSet::reset();  // @phan-suppress-current-line PhanAccessMethodInternal

        $code_base = self::createCodeBaseWithoutFunctions([]);
        $test_file = $this->createTempPhpFile("<?php\nregister_shutdown_function('a_nonexistent_callback_5649');\n");

        $output = self::analyzeFile($code_base, $test_file);
        $this->assertStringContainsString('PhanUndeclaredFunctionInCallable', $output);
    }
}
