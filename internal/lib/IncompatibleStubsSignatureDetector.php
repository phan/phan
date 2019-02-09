<?php

declare(strict_types=1);

use Phan\Analysis;
use Phan\CodeBase;
use Phan\Exception\FQSENException;
use Phan\Language\Element\Func;
use Phan\Language\Element\Method;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Output\Collector\BufferingCollector;
use Phan\Phan;

require_once __DIR__ . '/IncompatibleSignatureDetectorBase.php';

/**
 * This reads from a folder containing PHP stub files documenting internal extensions (e.g. those from PHPStorm)
 * to check if Phan's function signature map are up to date.
 */
class IncompatibleStubsSignatureDetector extends IncompatibleSignatureDetectorBase
{
    /** @var string a directory which contains stubs written in PHP for classes, functions, etc. of PHP modules (extensions)  */
    private $directory;

    /** @var CodeBase The code base within which we're operating */
    private $code_base;

    public function __construct(string $dir)
    {
        if (!is_dir($dir)) {
            echo "Could not find '$dir'\n";
            static::printUsageAndExit();
        }
        Phan::setIssueCollector(new BufferingCollector());

        $realpath = realpath($dir);
        if (!is_string($realpath)) {
            echo "Could not find realpath of '$dir'\n";
            static::printUsageAndExit();
            return;
        }
        $this->directory = $realpath;

        // TODO: Change to a more suitable configuration?
        $this->code_base = require(dirname(__DIR__) . '/../src/codebase.php');
    }

    /**
     * Check that this extracts the correct signature types from the folder.
     * @return void
     * @suppress PhanPluginMixedKeyNoKey
     */
    public function selfTest()
    {
        $failures = 0;
        $failures += $this->expectFunctionLikeSignaturesMatch('strlen', ['int', 'string' => 'string']);
        // $failures += $this->expectFunctionLikeSignaturesMatch('ob_clean', ['void']);
        $failures += $this->expectFunctionLikeSignaturesMatch('intdiv', ['int', 'numerator' => 'int', 'divisor' => 'int']);
        $failures += $this->expectFunctionLikeSignaturesMatch('ArrayIterator::seek', ['void', 'position' => 'int']);
        $failures += $this->expectFunctionLikeSignaturesMatch('Redis::hGet', ['false|string', 'key' => 'string', 'hashKey' => 'string']);
        if ($failures > 0) {
            exit(1);
        }
    }

    private function expectFunctionLikeSignaturesMatch(string $function_name, array $expected) : int
    {
        $actual = $this->parseFunctionLikeSignature($function_name);
        if ($expected !== $actual) {
            fprintf(STDERR, "Extraction failed for %s\nExpected: %s\nActual:   %s\n", $function_name, json_encode($expected) ?: 'invalid', json_encode($actual) ?: 'invalid');
            return 1;
        }
        return 0;
    }

    /** @var bool has this initialized and parsed all of the stubs yet? */
    private $initialized = false;

    /**
     * @return array<int,string>
     */
    private function getFileList() : array
    {
        $iterator = new \CallbackFilterIterator(
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $this->directory,
                    \RecursiveDirectoryIterator::FOLLOW_SYMLINKS
                )
            ),
            static function (\SplFileInfo $file_info) : bool {
                if ($file_info->getExtension() !== 'php') {
                    return false;
                }

                if (!$file_info->isFile() || !$file_info->isReadable()) {
                    $file_path = $file_info->getRealPath();
                    error_log("Unable to read file {$file_path}");
                    return false;
                }

                return true;
            }
        );

        // @phan-suppress-next-line PhanPartialTypeMismatchReturn
        return array_keys(iterator_to_array($iterator));
    }

    /**
     * Initialize the stub information to write by parsing the folder with Phan.
     *
     * @return void
     */
    public function initStubs()
    {
        if ($this->initialized) {
            return;
        }
        $this->initialized = true;

        $file_list = $this->getFileList();
        if (count($file_list) === 0) {
            fwrite(STDERR, "Could not find any files in $this->directory");
            static::printUsageAndExit();
        }
        sort($file_list);

        // TODO: Load without internal signatures
        $code_base = $this->code_base;
        foreach ($file_list as $path_to_stub) {
            fwrite(STDERR, "Loading stub $path_to_stub\n");
            try {
                Analysis::parseFile($code_base, $path_to_stub, false, null, /* is_php_internal_stub = false, so that we actually parse phpdoc */ false);
            } catch (Exception $e) {
                fprintf(STDERR, "Caught exception parsing %s: %s: %s\n", $path_to_stub, get_class($e), $e->getMessage());
                // throw $e;
            }
        }
        Analysis::analyzeFunctions($code_base);
    }

    /**
     * @return ?array
     * @throws FQSENException if signature map is invalid
     */
    public function parseMethodSignature(string $class_name, string $method_name)
    {
        $this->initStubs();
        if ($class_name[0] !== '\\') {
            $class_name = '\\' . $class_name;
        }

        $code_base = $this->code_base;
        $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString($class_name);
        if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
            static::debug("Could not find $class_name\n");
            return null;
        }
        $method_fqsen = FullyQualifiedMethodName::make($class_fqsen, $method_name);
        if (!$code_base->hasMethodWithFQSEN($method_fqsen)) {
            static::debug("Could not find $method_fqsen\n");
            return null;
        }
        $method = $code_base->getMethodByFQSEN($method_fqsen);
        // echo "Found $method_fqsen at " . $method->getFileRef()->getFile() . "\n";

        $method->ensureScopeInitialized($code_base);
        return $method->toFunctionSignatureArray();
    }

    /**
     * @return ?array
     * @throws FQSENException if $function_name is invalid
     */
    public function parseFunctionSignature(string $function_name)
    {
        $this->initStubs();
        $function_fqsen = FullyQualifiedFunctionName::fromFullyQualifiedString($function_name);
        $code_base = $this->code_base;
        if (!$code_base->hasFunctionWithFQSEN($function_fqsen)) {
            static::debug("Could not find $function_name\n");
            return null;
        }
        $function = $code_base->getFunctionByFQSEN($function_fqsen);
        $function->ensureScopeInitialized($code_base);
        return $function->toFunctionSignatureArray();
    }

    /**
     * @return array<string,array<int|string,string>>
     * @override
     */
    protected function getAvailableGlobalFunctionSignatures() : array
    {
        return $this->memoize(__METHOD__, /** @return array<string,array<int|string,string>> */ function () : array {
            $code_base = $this->code_base;
            $function_name_map = [];
            foreach ($code_base->getFunctionMap() as $func) {
                if (!($func instanceof Func)) {
                    throw new AssertionError('expected $func to be a Func');
                }
                $function_name = $func->getFQSEN()->getNamespacedName();
                $func->ensureScopeInitialized($code_base);
                $function_name_map[$function_name] = $func->toFunctionSignatureArray();
            }
            return $function_name_map;
        });
    }

    /**
     * @return array<string,array<int|string,string>>
     * @override
     */
    protected function getAvailableMethodSignatures() : array
    {
        return $this->memoize(__METHOD__, function () : array {
            $code_base = $this->code_base;
            $function_name_map = [];
            foreach ($code_base->getMethodSet() as $method) {
                if (!($method instanceof Method)) {
                    throw new AssertionError('expected $method to be a Method');
                }
                $function_name = $method->getClassFQSEN()->getNamespacedName() . '::' . $method->getName();
                $method->ensureScopeInitialized($code_base);
                $function_name_map[$function_name] = $method->toFunctionSignatureArray();
            }
            return $function_name_map;
        });
    }
}
