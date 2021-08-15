<?php

declare(strict_types=1);

use Phan\Analysis;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\FQSENException;
use Phan\Language\Context;
use Phan\Language\Element\ClassConstant;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Func;
use Phan\Language\Element\GlobalConstant;
use Phan\Language\Element\MarkupDescription;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Library\StringUtil;
use Phan\Output\Collector\BufferingCollector;
use Phan\Phan;

require_once __DIR__ . '/IncompatibleSignatureDetectorBase.php';

/**
 * This reads from a folder containing PHP stub files documenting internal extensions (e.g. those from php-src)
 * to check if Phan's function signature map are up to date.
 *
 * `php-ast` does not differentiate between echo statements and inline html.
 *
 * @phan-file-suppress PhanPluginRemoveDebugAny
 */
class IncompatibleRealStubsSignatureDetector extends IncompatibleSignatureDetectorBase
{
    /** @var string a directory which contains stubs (*.php.stub) written in PHP for classes, functions, etc. of PHP modules (extensions)  */
    private $directory;

    /** @var CodeBase The code base within which we're operating */
    private $code_base;

    public function __construct(string $dir)
    {
        if (!file_exists($dir)) {
            echo "Could not find '$dir'\n";
            static::printUsageAndExit();
        }
        if (!is_dir($dir)) {
            echo "'$dir' is not a directory\n";
            static::printUsageAndExit();
        }
        Phan::setIssueCollector(new BufferingCollector());
        // Disable Phan's own internal stubs, they interfere with loading stubs in the provided directories.
        Config::setValue('autoload_internal_extension_signatures', []);
        Config::setValue('target_php_version', PHP_VERSION_ID);

        $realpath = realpath($dir);
        if (!is_string($realpath)) {
            echo "Could not find realpath of '$dir'\n";
            static::printUsageAndExit();
            return;
        }
        $this->directory = $realpath;

        // NOTE: This is deliberately not using any of Phan's internal stub information.
        $this->code_base = new CodeBase([], [], [], [], []);
        $this->code_base->eagerlyLoadAllSignatures();
        $this->initStubs();
    }

    /**
     * Check that this extracts the correct signature types from the folder.
     * @suppress PhanPluginMixedKeyNoKey
     */
    public function selfTest(): void
    {
        fwrite(STDERR, "Running a test that this directory contains commonly used signature - ignore this if this is only for a single extension\n");
        $failures = 0;
        $failures += $this->expectFunctionLikeSignaturesMatch('strlen', ['int', 'string' => 'string']);
        // $failures += $this->expectFunctionLikeSignaturesMatch('ob_clean', ['void']);
        $failures += $this->expectFunctionLikeSignaturesMatch('intdiv', ['int', 'num1' => 'int', 'num2' => 'int']);
        $failures += $this->expectFunctionLikeSignaturesMatch('ArrayIterator::seek', ['void', 'position' => 'int']);
        if ($failures) {
            fwrite(STDERR, "Saw $failures incorrect or missing signatures, continuing\n");
        }
        /*
        if ($failures > 1) {
            exit(1);
        }
         */
    }

    /**
     * @param array<int|string,string> $expected the Phan signature information in the stubs
     */
    private function expectFunctionLikeSignaturesMatch(string $function_name, array $expected): int
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
    private function getFileList(): array
    {
        if (is_file($this->directory)) {
            return [$this->directory];
        }
        $iterator = new \CallbackFilterIterator(
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $this->directory,
                    \RecursiveDirectoryIterator::FOLLOW_SYMLINKS
                )
            ),
            static function (SplFileInfo $file_info): bool {
                if (!\str_ends_with($file_info->getBaseName(), '.stub.php')) {
                    return false;
                }

                if (!$file_info->isFile() || !$file_info->isReadable()) {
                    $file_path = $file_info->getRealPath();
                    error_log("Unable to read file {$file_path}");
                    return false;
                }
                echo "Found {$file_info->getRealPath()}\n";

                return true;
            }
        );

        // @phan-suppress-next-line PhanPartialTypeMismatchReturn
        return array_keys(iterator_to_array($iterator));
    }

    /**
     * Initialize the stub information to write by parsing the folder with Phan.
     */
    public function initStubs(): void
    {
        if ($this->initialized) {
            return;
        }
        $this->initialized = true;

        $file_list = $this->getFileList();
        if (count($file_list) === 0) {
            fwrite(STDERR, "Could not find any files ending in .stub.php in $this->directory");
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
        // After parsing all files, mark stub functions as having return statements so that the inferred type won't be void.
        self::markAllStubsAsNonVoid($code_base);

        Analysis::analyzeFunctions($code_base);
    }

    /**
     * @return ?array<mixed,string>
     * @throws FQSENException if signature map is invalid
     */
    public function parseMethodSignature(string $class_name, string $method_name): ?array
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
        $class = $code_base->getClassByFQSEN($class_fqsen);
        for ($alternate_id = 1; $class->isPHPInternal(); $alternate_id++) {
            $alternate_class_fqsen = $class_fqsen->withAlternateId($alternate_id);
            if (!$code_base->hasClassWithFQSEN($alternate_class_fqsen)) {
                break;
            }
            $class = $code_base->getClassByFQSEN($alternate_class_fqsen);
        }
        if ($class->isPHPInternal()) {
            static::debug("Could not find $class_name except from reflection\n");
            return null;
        }

        $method_fqsen = FullyQualifiedMethodName::make($class_fqsen, $method_name);
        if (!$code_base->hasMethodWithFQSEN($method_fqsen)) {
            static::debug("Could not find $method_fqsen\n");
            return null;
        }
        $method = $code_base->getMethodByFQSEN($method_fqsen);

        $method->ensureScopeInitialized($code_base);
        return $method->toFunctionSignatureArray();
    }

    /**
     * @return ?array<mixed,string>
     * @throws FQSENException if $function_name is invalid
     */
    public function parseFunctionSignature(string $function_name): ?array
    {
        $this->initStubs();
        $function_name = preg_replace("/'.*$/D", '', $function_name);  // remove alternate id
        $function_fqsen = FullyQualifiedFunctionName::fromFullyQualifiedString($function_name);
        $code_base = $this->code_base;
        if (!$code_base->hasFunctionWithFQSEN($function_fqsen)) {
            static::debug("Could not find $function_name\n");
            return null;
        }
        $function = $code_base->getFunctionByFQSEN($function_fqsen);
        $function->ensureScopeInitialized($code_base);
        for ($alternate_id = 1; $function->isPHPInternal(); $alternate_id++) {
            $alternate_fqsen = $function_fqsen->withAlternateId($alternate_id);
            if (!$code_base->hasFunctionWithFQSEN($alternate_fqsen)) {
                break;
            }
            $function = $code_base->getFunctionByFQSEN($alternate_fqsen);
        }
        if ($function->isPHPInternal()) {
            static::debug("Could not find $function_name except from reflection\n");
            return null;
        }
        return $function->toFunctionSignatureArray();
    }

    /**
     * @return array<string,array<int|string,string>>
     * @override
     */
    public function getAvailableGlobalFunctionSignatures(): array
    {
        return $this->memoize(__METHOD__, /** @return array<string,array<int|string,string>> */ function (): array {
            $code_base = $this->code_base;
            $function_name_map = [];
            foreach ($code_base->getFunctionMap() as $func) {
                if (!($func instanceof Func)) {
                    throw new AssertionError('expected $func to be a Func');
                }
                $function_name = $func->getFQSEN()->getNamespacedName();
                // echo "Saw $function_name at {$func->getContext()}\n";
                $func->ensureScopeInitialized($code_base);
                try {
                    $function_name_map[$function_name] = $func->toFunctionSignatureArray();
                } catch (\InvalidArgumentException $e) {
                    fwrite(STDERR, "TODO: Fix signature for {$func->getFQSEN()}: {$e->getMessage()}\n");
                }
            }
            return $function_name_map;
        });
    }

    /**
     * @return array<string,array<int|string,string>>
     * @override
     */
    public function getAvailableMethodSignatures(): array
    {
        return $this->memoize(__METHOD__, /** @return array<string,array<int|string,string>> */ function (): array {
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

    /**
     * @return array<string,string>
     */
    protected function getAvailablePropertyPHPDocSummaries(): array
    {
        return $this->memoize(__METHOD__, /** @return array<string,string> */ function (): array {
            $code_base = $this->code_base;
            $map = [];
            $classes = array_merge(
                iterator_to_array($code_base->getInternalClassMap(), false),
                iterator_to_array($code_base->getUserDefinedClassMap(), false)
            );
            foreach ($classes as $class) {
                foreach ($class->getPropertyMap($code_base) as $property) {
                    if ($property->getFQSEN() !== $property->getDefiningFQSEN()) {
                        // Skip this, Phan should be able to inherit this long term
                        continue;
                    }
                    if (!($property instanceof Property)) {
                        throw new AssertionError('expected $property to be a Property');
                    }
                    $description = (string)MarkupDescription::extractDescriptionFromDocComment($property, null);
                    $description = preg_replace('(^`@var [^`]*`\s*)', '', $description);
                    $description = self::removeBoilerplateFromDescription($description);
                    if (strlen($description) === 0) {
                        continue;
                    }
                    $property_name = ltrim((string)$property->getFQSEN(), "\\");
                    if (preg_match(self::FUNCTIONLIKE_BLACKLIST, $property_name)) {
                        continue;
                    }
                    echo "$property_name: $description\n";
                    $map[$property_name] = $description;
                }
            }
            return $map;
        });
    }

    /**
     * @return array<string,string>
     */
    protected function getAvailableClassPHPDocSummaries(): array
    {
        return $this->memoize(__METHOD__, /** @return array<string,string> */ function (): array {
            $code_base = $this->code_base;
            $map = [];
            $classes = array_merge(
                iterator_to_array($code_base->getInternalClassMap(), false),
                iterator_to_array($code_base->getUserDefinedClassMap(), false)
            );
            foreach ($classes as $class) {
                echo "Looking at {$class->getFQSEN()}\n";
                if (!($class instanceof Clazz)) {
                    throw new AssertionError('expected $class to be a Clazz');
                }
                $description = (string)MarkupDescription::extractDescriptionFromDocComment($class, null);
                $description = self::removeBoilerplateFromDescription($description);
                if (strlen($description) === 0) {
                    continue;
                }
                $class_name = ltrim((string)$class->getFQSEN(), "\\");
                if (preg_match(self::FUNCTIONLIKE_BLACKLIST, $class_name)) {
                    continue;
                }
                echo "$class_name: $description\n";
                $map[$class_name] = $description;
            }
            return $map;
        });
    }

    /**
     * Removes boilerplate such as minimum PHP versions from summary text
     */
    public static function removeBoilerplateFromDescription(string $description): string
    {
        return preg_replace('@\((PECL|PHP|No version information)[^)]*\)\s*<br/>\s*@im', '', $description);
    }

    /**
     * @return array<string,string>
     */
    protected function getAvailableConstantPHPDocSummaries(): array
    {
        return $this->memoize(__METHOD__, /** @return array<string,string> */ function (): array {
            $code_base = $this->code_base;
            $map = [];
            foreach ($code_base->getGlobalConstantMap() as $const) {
                if (!($const instanceof GlobalConstant)) {
                    throw new AssertionError('expected $const to be a GlobalConstant');
                }
                $description = (string)MarkupDescription::extractDescriptionFromDocComment($const, null);
                $description = self::removeBoilerplateFromDescription($description);
                if (strlen($description) === 0) {
                    continue;
                }
                $const_name = ltrim((string)$const->getFQSEN(), "\\");
                if (preg_match(self::FUNCTIONLIKE_BLACKLIST, $const_name)) {
                    continue;
                }
                echo "$const_name: $description\n";
                $map[$const_name] = $description;
            }
            foreach ($code_base->getClassMapMap() as $class_map) {
                foreach ($class_map->getClassConstantMap() as $const) {
                    if (!($const instanceof ClassConstant)) {
                        throw new AssertionError('expected $const to be a ClassConstant');
                    }
                    $description = (string)MarkupDescription::extractDescriptionFromDocComment($const, null);
                    // Remove the markup added by MarkdupDescription
                    $description = preg_replace('(^`@var [^`]*`\s*)', '', $description);

                    $description = self::removeBoilerplateFromDescription($description);
                    if (strlen($description) === 0) {
                        continue;
                    }

                    $const_name = ltrim((string)$const->getFQSEN(), "\\");
                    if (preg_match(self::FUNCTIONLIKE_BLACKLIST, $const_name)) {
                        continue;
                    }
                    echo "$const_name: $description\n";
                    $map[$const_name] = $description;
                }
            }
            return $map;
        });
    }

    /**
     * Get available function and method summaries from the stubs directory.
     *
     * @return array<string,string>
     */
    protected function getAvailableMethodPHPDocSummaries(): array
    {
        return $this->memoize(__METHOD__, /** @return array<string,string> */ function (): array {
            $code_base = $this->code_base;
            $function_name_map = [];
            foreach ($code_base->getMethodSet() as $method) {
                if (!($method instanceof Method)) {
                    throw new AssertionError('expected $method to be a Method');
                }
                $description = (string)MarkupDescription::extractDescriptionFromDocComment($method, null);
                $description = self::removeBoilerplateFromDescription($description);
                if (strlen($description) === 0) {
                    continue;
                }
                $function_name = $method->getClassFQSEN()->getNamespacedName() . '::' . $method->getName();
                if (preg_match(self::FUNCTIONLIKE_BLACKLIST, $function_name)) {
                    continue;
                }
                echo "$function_name: $description\n";
                $function_name_map[$function_name] = $description;
            }
            foreach ($code_base->getFunctionMap() as $function) {
                if (!($function instanceof Func)) {
                    throw new AssertionError('expected $function to be a Func');
                }
                $description = MarkupDescription::extractDescriptionFromDocComment($function, null);
                if (!StringUtil::isNonZeroLengthString($description)) {
                    continue;
                }
                $function_name = ltrim((string)$function->getFQSEN(), "\\");
                if (preg_match(self::FUNCTIONLIKE_BLACKLIST, $function_name)) {
                    continue;
                }
                echo "$function_name: $description\n";
                $function_name_map[$function_name] = $description;
            }
            return $function_name_map;
        });
    }

    /**
     * @param array<mixed,string> $arguments_from_phan
     * @return array<mixed,string>
     */
    protected function updateSignatureParamNames(string $function_like_name, array $arguments_from_phan): array
    {
        $arguments_from_external_stub = $this->parseFunctionLikeSignature($function_like_name);
        if (is_null($arguments_from_external_stub)) {
            return $arguments_from_phan;
        }
        /*
        $repr = self::encodeSignatureArguments($arguments_from_phan);
        if (str_contains($repr, '...')) {
            return $arguments_from_phan;
        }
         */
        $count = count($arguments_from_phan);
        $keys = array_keys($arguments_from_phan);
        $keys_external = array_keys($arguments_from_external_stub);
        $new_arguments_from_phan = [];
        foreach ($keys as $i => $param_name) {
            $type = $arguments_from_phan[$param_name];
            if (!isset($keys_external[$i])) {
                $new_arguments_from_phan[$param_name] = $type;
                continue;
            }
            $param_name_external = $keys_external[$i];
            if (is_string($param_name) && is_string($param_name_external)) {
                // FIXME doesn't handle &...w_
                if (str_starts_with($param_name, '&w_')) {
                    $param_name_external = preg_replace('/^\&(w_)?/', '&w_', $param_name_external);
                } elseif (str_starts_with($param_name, '&rw_')) {
                    $param_name_external = preg_replace('/^\&(rw_)?/', '&rw_', $param_name_external);
                } elseif (str_starts_with($param_name, '&r_')) {
                    $param_name_external = preg_replace('/^\&(r_)?/', '&r_', $param_name_external);
                }
                $param_name_external = self::copyParamModifiers($param_name_external, $param_name);
            }
            $new_arguments_from_phan[$param_name_external] = $type;
        }
        if (count($new_arguments_from_phan) !== $count) {
            static::info("Could not rename signature for $function_like_name due to param name conflict\n");
            return $arguments_from_phan;
        }
        return $new_arguments_from_phan;
    }


    /**
     * Migrate `&...old_param=` to `&...new_param=` for an alternate signature
     */
    private static function copyParamModifiers(string $new, string $old): string
    {
        $new = trim($new, '&.=');
        if ($old[-1] === '=') {
            $new .= '=';
        }
        $i = strspn($old, '&.');
        if ($i > 0) {
            return substr($old, 0, $i) . $new;
        }
        return $new;
    }

    /**
     * Update the function/method signatures using the subclass's source of type information
     */
    public function updateFunctionSignaturesParamNames(): void
    {
        $phan_signatures = static::readSignatureMap();
        $new_signatures = [];
        foreach ($phan_signatures as $method_name => $arguments) {
            /*
            if (strpos($method_name, "'") !== false || isset($phan_signatures["$method_name'1"])) {
                // Don't update functions/methods with alternate
                $new_signatures[$method_name] = $arguments;
                continue;
            }
             */
            try {
                $new_signatures[$method_name] = $this->updateSignatureParamNames($method_name, $arguments);
            } catch (InvalidArgumentException | FQSENException $e) {
                fwrite(STDERR, "Unexpected invalid signature for $method_name, skipping: $e\n");
            }
        }
        $new_signature_path = ORIGINAL_SIGNATURE_PATH . '.new';
        static::info("Saving modified function signatures to $new_signature_path (updating param and return types)\n");
        static::saveSignatureMap($new_signature_path, $new_signatures);
        $deltas = ['70', '71', '72', '73', '74', '80'];
        foreach ($deltas as $delta) {
            $delta_path = dirname(__DIR__, 2) . "/src/Phan/Language/Internal/FunctionSignatureMap_php{$delta}_delta.php";
            $delta_contents = require($delta_path);
            // TODO: Also update the changed section
            foreach (['added', 'removed'] as $section) {
                foreach ($delta_contents[$section] as $method_name => $arguments) {
                    /*
                    if (strpos($method_name, "'") !== false || isset($phan_signatures["$method_name'1"])) {
                        // Don't update functions/methods with alternate
                        continue;
                    }
                     */
                    try {
                        $delta_contents[$section][$method_name] = $this->updateSignatureParamNames($method_name, $arguments);
                    } catch (InvalidArgumentException | FQSENException $e) {
                        fwrite(STDERR, "Unexpected invalid signature for $method_name for $delta_path, skipping: $e\n");
                    }
                }
            }
            $new_delta_path = "$delta_path.new";
            static::info("Saving modified function signature deltas to $new_delta_path (updating param names)\n");
            static::saveSignatureDeltaMap($new_delta_path, $delta_path, $delta_contents);
        }
    }

    /**
     * @param array<mixed,string> $arguments_from_phan
     * @return array<mixed,string>
     * @override
     */
    protected function updateSignature(string $function_like_name, array $arguments_from_phan): array
    {
        $return_type = $arguments_from_phan[0];
        $arguments_from_external_stub = $this->parseFunctionLikeSignature($function_like_name);
        if (is_null($arguments_from_external_stub)) {
            return $arguments_from_phan;
        }
        if ($return_type === '') {
            $external_stub_return_type = $arguments_from_external_stub[0] ?? '';
            if ($external_stub_return_type !== '') {
                static::debug("A better Phan return type for $function_like_name is " . $external_stub_return_type . "\n");
                $arguments_from_phan[0] = $external_stub_return_type;
            }
        }
        $code_base = $this->code_base;
        $global_context = new Context();

        $param_index = 0;
        $arguments_from_external_stub_list = array_values($arguments_from_external_stub);  // keys are 0, 1, 2,...
        $arguments_from_external_stub_names = array_keys($arguments_from_external_stub);  // keys are 0, 1, 2,...
        foreach ($arguments_from_phan as $param_name => $param_type_from_phan) {
            $original_param_name = $param_name;

            // after incrementing param_index
            $param_from_external_stub_name = $arguments_from_external_stub_names[$param_index] ?? null;
            if ($param_index !== 0 && is_string($param_from_external_stub_name)) {
                $param_name = preg_replace('/^(rw|r|w)_/', '', trim((string)$param_name, '.=&'));
                $param_from_external_stub_name = trim($param_from_external_stub_name, '.=&');
                if ($param_from_external_stub_name !== $param_name) {
                    echo "Name mismatch for $function_like_name: #$param_index is \$$param_name in Phan, \$$param_from_external_stub_name in source\n";
                }
            }
            $param_from_external_stub = (string)($arguments_from_external_stub_list[$param_index] ?? '');

            if ($param_from_external_stub !== '') {
                $external_stub_type = UnionType::fromStringInContext($param_from_external_stub, $global_context, Type::FROM_TYPE);
                $phan_type = UnionType::fromStringInContext($param_type_from_phan, $global_context, Type::FROM_PHPDOC);
                if (is_string($original_param_name) && strpos($original_param_name, '...') !== false) {
                    $phan_type = $phan_type->asListTypes();
                }
                foreach ($phan_type->getTypeSet() as $phan_type_elem) {
                    if (!$phan_type_elem->asPHPDocUnionType()->isStrictSubtypeOf($code_base, $external_stub_type)) {
                        if ($param_index > 0) {
                            echo "A better Phan param type for $function_like_name (for param #$param_index called \$$original_param_name) is $param_from_external_stub (previously $param_type_from_phan)\n";
                        } else {
                            echo "A better Phan return type for $function_like_name is $param_from_external_stub (previously $param_type_from_phan)\n";
                        }
                        $arguments_from_phan[$param_name] = $param_from_external_stub;
                        break;
                    }
                }
            }
            $param_index++;
        }
        if (count($arguments_from_external_stub) > count($arguments_from_phan)) {
            $repr = self::encodeSignatureArguments($arguments_from_external_stub);
            if (strpos($repr, '...') === false) {
                echo "There are more arguments for $function_like_name: $repr\n";
            }
        }
        return $arguments_from_phan;
    }

    /**
     * @return array<string,array<int|string,string>>
     * @override
     */
    public static function readSignatureMap(): array
    {
        // TODO: Preserve the original case
        return require(dirname(__DIR__, 2) . '/src/Phan/Language/Internal/FunctionSignatureMap.php');
        // return UnionType::internalFunctionSignatureMap(PHP_VERSION_ID);
    }
}
