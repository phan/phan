<?php

declare(strict_types=1);

namespace Phan\Tests\Plugin;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
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
 * Tests of when plugins are notified of lazily loaded internal functions (`ConfigPluginSet::handleLazyLoadInternalFunction()`).
 */
final class LazyLoadHooksTest extends TestBase
{
    /**
     * The $GLOBALS key where the test plugins record the names of the functions they are notified of.
     */
    private const NOTIFIED_FUNCTIONS_KEY = 'phan_lazy_load_hooks_test_notified_functions';

    /**
     * A plugin that records the names of the functions it is notified of.
     */
    private const RECORDING_PLUGIN = <<<'PHP'
<?php
use Phan\CodeBase;
use Phan\Language\Element\Func;
use Phan\PluginV3;
use Phan\PluginV3\HandleLazyLoadInternalFunctionCapability;

return new class extends PluginV3 implements HandleLazyLoadInternalFunctionCapability {
    public function handleLazyLoadInternalFunction(CodeBase $code_base, Func $function): void
    {
        $GLOBALS['phan_lazy_load_hooks_test_notified_functions'][] = $function->getName();
    }
};
PHP;

    /**
     * A plugin that loads quotemeta() in beforeAnalyze() and records the names of the functions it is notified of.
     */
    private const BEFORE_ANALYZE_PLUGIN = <<<'PHP'
<?php
use Phan\CodeBase;
use Phan\Language\Element\Func;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\PluginV3;
use Phan\PluginV3\BeforeAnalyzeCapability;
use Phan\PluginV3\HandleLazyLoadInternalFunctionCapability;

return new class extends PluginV3 implements BeforeAnalyzeCapability, HandleLazyLoadInternalFunctionCapability {
    public function beforeAnalyze(CodeBase $code_base): void
    {
        $code_base->hasFunctionWithFQSEN(FullyQualifiedFunctionName::fromFullyQualifiedString('\\quotemeta'));
    }

    public function handleLazyLoadInternalFunction(CodeBase $code_base, Func $function): void
    {
        $GLOBALS['phan_lazy_load_hooks_test_notified_functions'][] = $function->getName();
    }
};
PHP;

    /** @var list<string> */
    private array $temp_files = [];

    public function setUp(): void
    {
        parent::setUp();
        Config::reset();  // @phan-suppress-current-line PhanAccessMethodInternal
        ConfigPluginSet::reset();  // @phan-suppress-current-line PhanAccessMethodInternal
        Type::clearAllMemoizations();
        FullyQualifiedGlobalStructuralElement::clearPreferredNameMap();
        $GLOBALS[self::NOTIFIED_FUNCTIONS_KEY] = [];
    }

    public function tearDown(): void
    {
        foreach ($this->temp_files as $path) {
            if (\is_file($path)) {
                \unlink($path);
            }
        }
        $this->temp_files = [];
        unset($GLOBALS[self::NOTIFIED_FUNCTIONS_KEY]);
        GlobalScope::reset();
        Config::reset();  // @phan-suppress-current-line PhanAccessMethodInternal
        ConfigPluginSet::reset();  // @phan-suppress-current-line PhanAccessMethodInternal
        Type::clearAllMemoizations();
        FullyQualifiedGlobalStructuralElement::clearPreferredNameMap();
        parent::tearDown();
    }

    private function createTempPhpFile(string $contents): string
    {
        $path = \tempnam(\sys_get_temp_dir(), 'phan_lazy_load_');
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
            require_once(\dirname(__DIR__, 3) . '/src/codebase.php');
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
     * Configures the plugin with the given source as the only user plugin.
     */
    private function usePlugin(string $plugin_source): void
    {
        Config::setValue('plugins', [$this->createTempPhpFile($plugin_source)]);
        ConfigPluginSet::reset();  // @phan-suppress-current-line PhanAccessMethodInternal
    }

    /**
     * Loads the internal function $name, which must not be loaded yet, into $code_base.
     * @suppress PhanThrowTypeAbsentForCall
     */
    private function loadFunction(CodeBase $code_base, string $name): void
    {
        $fqsen = FullyQualifiedFunctionName::fromFullyQualifiedString('\\' . $name);
        $this->assertFalse($code_base->getFunctionMap()->offsetExists($fqsen), "$name() is already loaded");
        $this->assertTrue($code_base->hasFunctionWithFQSEN($fqsen));
    }

    /**
     * @return list<string> the names of the functions the test plugins were notified of
     */
    private function getNotifiedFunctions(): array
    {
        $notified = $GLOBALS[self::NOTIFIED_FUNCTIONS_KEY];
        $this->assertIsArray($notified);
        return \array_values(\array_map('strval', $notified));
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

    /**
     * A plugin is notified of an internal function loaded during the analysis,
     * but not of one loaded after the analysis finished.
     */
    public function testNotNotifiedAfterAnalysis(): void
    {
        $this->usePlugin(self::RECORDING_PLUGIN);
        $code_base = self::createCodeBaseWithoutFunctions([]);
        $test_file = $this->createTempPhpFile("<?php\necho levenshtein('a', 'b');\n");

        self::analyzeFile($code_base, $test_file);
        $this->assertContains('levenshtein', $this->getNotifiedFunctions());

        $this->loadFunction($code_base, 'soundex');
        $this->assertNotContains('soundex', $this->getNotifiedFunctions());
    }

    /**
     * A plugin is not notified of an internal function loaded after ConfigPluginSet::beforeAnalyze() returned
     * (daemon mode and the language server parse files again in that state).
     */
    public function testNotNotifiedAfterBeforeAnalyze(): void
    {
        $this->usePlugin(self::RECORDING_PLUGIN);
        $code_base = self::createCodeBaseWithoutFunctions([]);

        ConfigPluginSet::instance()->beforeAnalyze($code_base);
        $this->loadFunction($code_base, 'metaphone');
        $this->assertNotContains('metaphone', $this->getNotifiedFunctions());

        // The same plugin is notified of a function loaded in the analysis phase.
        ConfigPluginSet::instance()->beginAnalyzePhase();  // @phan-suppress-current-line PhanAccessMethodInternal
        $this->loadFunction($code_base, 'similar_text');
        ConfigPluginSet::instance()->endAnalyzePhase();  // @phan-suppress-current-line PhanAccessMethodInternal
        $this->assertContains('similar_text', $this->getNotifiedFunctions());
    }

    /**
     * A plugin is notified of an internal function that a plugin loads in beforeAnalyze(),
     * and not of one loaded after ConfigPluginSet::beforeAnalyze() returned.
     */
    public function testNotifiedOfFunctionLoadedInBeforeAnalyze(): void
    {
        $this->usePlugin(self::BEFORE_ANALYZE_PLUGIN);
        $code_base = self::createCodeBaseWithoutFunctions([]);

        ConfigPluginSet::instance()->beforeAnalyze($code_base);
        $this->assertContains('quotemeta', $this->getNotifiedFunctions());

        $this->loadFunction($code_base, 'nl2br');
        $this->assertNotContains('nl2br', $this->getNotifiedFunctions());
    }
}
