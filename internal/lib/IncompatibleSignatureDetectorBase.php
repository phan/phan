<?php
declare(strict_types=1);

use Phan\Memoize;

define('ORIGINAL_SIGNATURE_PATH', dirname(dirname(__DIR__)) . '/src/Phan/Language/Internal/FunctionSignatureMap.php');
define('ORIGINAL_FUNCTION_DOCUMENTATION_PATH', dirname(dirname(__DIR__)) . '/src/Phan/Language/Internal/FunctionDocumentationMap.php');
define('ORIGINAL_CONSTANT_DOCUMENTATION_PATH', dirname(dirname(__DIR__)) . '/src/Phan/Language/Internal/ConstantDocumentationMap.php');
define('ORIGINAL_CLASS_DOCUMENTATION_PATH', dirname(dirname(__DIR__)) . '/src/Phan/Language/Internal/ClassDocumentationMap.php');

/**
 * Implementations of this can be used to check Phan's function signature map.
 *
 * They do the following:
 *
 * - Load signatures from an external source
 * - Compare the signatures against Phan's to report incomplete or inaccurate signatures of Phan itself (or the external signature)
 *
 * TODO: could extend this to properties (the use of properties in extensions is rare).
 * TODO: Fix zoookeeperconfig in phpdoc-en svn repo
 *
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */
abstract class IncompatibleSignatureDetectorBase
{
    use Memoize;

    const FUNCTIONLIKE_BLACKLIST = '@' .
        '(^_*PHPSTORM)|PS_UNRESERVE_PREFIX|' .
        '(^(ereg|expression|getsession|hrtime_|imageps|mssql_|mysql_|split|sql_regcase|sybase|xmldiff_))|' .
        '(^closure_)|' .  // Phan's representation of a closure
        '\.|,' .  // a literal `.` or `,`
        '@';

    /** @var array<string,string> maps aliases to originals - only set for xml parser */
    protected $aliases = [];

    /**
     * @return void (does not return)
     */
    protected static function printUsageAndExit(int $exit_code = 1)
    {
        global $argv;
        $program_name = $argv[0];
        $msg = <<<EOT
Usage: $program_name command [...args]
  $program_name sort
    Sort the internal signature map in place

  $program_name help
    Print this help message

  $program_name update-stubs path/to/stubs-dir
    Update any of Phan's missing signatures based on a checkout of a directory with stubs for extensions.

  $program_name update-svn path/to/phpdoc_svn_dir
    Update any of Phan's missing signatures based on a checkout of the docs.php.net source repo.

    phpdoc_svn_dir can be checked out via 'svn checkout https://svn.php.net/repository/phpdoc/modules/doc-en phpdoc-en' (subversion must be installed)
    (and updated via 'svn update')
    see http://doc.php.net/tutorial/structure.php

  $program_name update-descriptions-svn path/to/phpdoc_svn_dir
    Update Phan's descriptions for functions/methods based on the docs.php.net source repo.

EOT;
        fwrite(STDERR, $msg);
        exit($exit_code);
    }

    /**
     * Update phpdoc summaries of elements with the docs from php.net
     *
     * @return void
     */
    protected function updatePHPDocSummaries()
    {
        $this->updatePHPDocFunctionSummaries();
        $this->updatePHPDocConstantSummaries();
        $this->updatePHPDocClassSummaries();
    }

    /**
     * Merge signatures from $new into $old if the case-insensitive signatures don't already exist in $old.
     *
     * Returns the resulting sorted signature map.
     *
     * @template T
     * @param array<string,T> $old
     * @param array<string,T> $new
     * @return array<string,T>
     */
    public static function mergeSignatureMaps(array $old, array $new)
    {
        $normalized_old = [];
        foreach ($old as $key => $_) {
            // NOTE: This won't work for the name part of constants, but low importance.
            $normalized_old[strtolower($key)] = true;
        }
        foreach ($new as $key => $value) {
            if (isset($normalized_old[strtolower($key)])) {
                continue;
            }
            $old[$key] = $value;
        }
        self::sortSignatureMap($old);
        return $old;
    }

    /**
     * @return void
     */
    protected function updatePHPDocFunctionSummaries()
    {
        $old_function_documentation = $this->readFunctionDocumentationMap();
        $new_function_documentation = $this->getAvailableMethodPHPDocSummaries();
        $new_function_documentation = self::mergeSignatureMaps($old_function_documentation, $new_function_documentation);

        $new_function_documentation_path = ORIGINAL_FUNCTION_DOCUMENTATION_PATH . '.new';
        static::info("Saving modified function descriptions to $new_function_documentation_path\n");
        static::saveFunctionDocumentationMap($new_function_documentation_path, $new_function_documentation);
    }

    /**
     * Returns short phpdoc summaries of function and method signatures
     *
     * @return array<string,string>
     */
    abstract protected function getAvailableMethodPHPDocSummaries() : array;

    /**
     * @return void
     */
    protected function updatePHPDocConstantSummaries()
    {
        $old_constant_documentation = $this->readConstantDocumentationMap();
        $new_constant_documentation = $this->getAvailableConstantPHPDocSummaries();
        $new_constant_documentation = self::mergeSignatureMaps($old_constant_documentation, $new_constant_documentation);

        self::sortSignatureMap($new_constant_documentation);

        $new_constant_documentation_path = ORIGINAL_CONSTANT_DOCUMENTATION_PATH . '.new';
        static::info("Saving modified constant descriptions to $new_constant_documentation_path\n");
        static::saveConstantDocumentationMap($new_constant_documentation_path, $new_constant_documentation);
    }

    /**
     * @return array<string,string>
     */
    abstract protected function getAvailableConstantPHPDocSummaries() : array;

    /**
     * @return void
     */
    protected function updatePHPDocClassSummaries()
    {
        $old_class_documentation = $this->readClassDocumentationMap();
        $new_class_documentation = $this->getAvailableClassPHPDocSummaries();
        $new_class_documentation = self::mergeSignatureMaps($old_class_documentation, $new_class_documentation);

        self::sortSignatureMap($new_class_documentation);

        $new_class_documentation_path = ORIGINAL_CLASS_DOCUMENTATION_PATH . '.new';
        static::info("Saving modified class descriptions to $new_class_documentation_path\n");
        static::saveClassDocumentationMap($new_class_documentation_path, $new_class_documentation);
    }

    /**
     * @return array<string,string>
     */
    abstract protected function getAvailableClassPHPDocSummaries() : array;


    /** @return void */
    public function updateFunctionSignatures()
    {
        $phan_signatures = static::readSignatureMap();
        $new_signatures = [];
        foreach ($phan_signatures as $method_name => $arguments) {
            if (stripos($method_name, "'") !== false || isset($phan_signatures["$method_name'1"])) {
                // Don't update functions/methods with alternate
                $new_signatures[$method_name] = $arguments;
                continue;
            }
            $new_signatures[$method_name] = static::updateSignature($method_name, $arguments);
        }
        $new_signature_path = ORIGINAL_SIGNATURE_PATH . '.new';
        static::info("Saving modified function signatures to $new_signature_path (updating param and return types)\n");
        static::saveSignatureMap($new_signature_path, $new_signatures);
    }

    /**
     * @param array<mixed,string> $arguments_from_phan
     * @return array<mixed,string>
     */
    private function updateSignature(string $function_like_name, array $arguments_from_phan)
    {
        $return_type = $arguments_from_phan[0];
        $arguments_from_svn = null;
        if ($return_type === '') {
            $arguments_from_svn = $arguments_from_svn ?? $this->parseFunctionLikeSignature($function_like_name);
            if (is_null($arguments_from_svn)) {
                return $arguments_from_phan;
            }
            $svn_return_type = $arguments_from_svn[0] ?? '';
            if ($svn_return_type !== '') {
                static::debug("A better Phan return type for $function_like_name is " . $svn_return_type . "\n");
                $arguments_from_phan[0] = $svn_return_type;
            }
        }
        $param_index = 0;
        foreach ($arguments_from_phan as $param_name => $param_type_from_phan) {
            if ($param_name === 0) {
                continue;
            }
            $param_index++;
            if ($param_type_from_phan !== '') {
                continue;
            }
            $arguments_from_svn = $arguments_from_svn ?? $this->parseFunctionLikeSignature($function_like_name);
            if (is_null($arguments_from_svn)) {
                return $arguments_from_phan;
            }
            $arguments_from_svn_list = array_values($arguments_from_svn);  // keys are 0, 1, 2,...
            $param_from_svn = $arguments_from_svn_list[$param_index] ?? '';
            if ($param_from_svn !== '') {
                static::debug("A better Phan param type for $function_like_name (for param #$param_index called \$$param_name) is $param_from_svn\n");
                $arguments_from_phan[$param_name] = $param_from_svn;
            }
        }
        // TODO: Update param types
        return $arguments_from_phan;
    }


    /** @return void */
    public function addMissingFunctionLikeSignatures()
    {
        $phan_signatures = static::readSignatureMap();
        $this->addMissingGlobalFunctionSignatures($phan_signatures);
        $this->addMissingMethodSignatures($phan_signatures);
        $new_signature_path = ORIGINAL_SIGNATURE_PATH . '.extra_signatures';
        static::info("Saving function signatures with extra paths to $new_signature_path (updating param and return types)\n");
        static::sortSignatureMap($phan_signatures);
        static::saveSignatureMap($new_signature_path, $phan_signatures);
    }

    /**
     * @param array<string,array<int|string,string>> &$phan_signatures
     * @return void
     */
    protected function addMissingGlobalFunctionSignatures(array &$phan_signatures)
    {
        $phan_signatures_lc = static::getLowercaseSignatureMap($phan_signatures);
        foreach ($this->getAvailableGlobalFunctionSignatures() as $function_name => $method_signature) {
            if (isset($phan_signatures_lc[strtolower($function_name)])) {
                continue;
            }
            if (\preg_match(static::FUNCTIONLIKE_BLACKLIST, $function_name)) {
                continue;
            }
            $phan_signatures[$function_name] = $method_signature;
        }
    }

    /**
     * @return array<string,array<int|string,string>>
     */
    abstract protected function getAvailableGlobalFunctionSignatures() : array;

    /**
     * @param array<string,array<int|string,string>> &$phan_signatures
     * @return void
     */
    protected function addMissingMethodSignatures(array &$phan_signatures)
    {
        $phan_signatures_lc = static::getLowercaseSignatureMap($phan_signatures);
        foreach ($this->getAvailableMethodSignatures() as $method_name => $method_signature) {
            if (isset($phan_signatures_lc[strtolower($method_name)])) {
                continue;
            }
            if (\preg_match(static::FUNCTIONLIKE_BLACKLIST, $method_name)) {
                continue;
            }
            $phan_signatures[$method_name] = $method_signature;
        }
    }

    /**
     * @return array<string,array<int|string,string>>
     */
    abstract protected function getAvailableMethodSignatures() : array;

    /**
     * @param array<string,array<int|string,string>> $phan_signatures
     * @return array<string,array<int|string,string>>
     */
    protected static function getLowercaseSignatureMap(array $phan_signatures) : array
    {
        $phan_signatures_lc = [];
        foreach ($phan_signatures as $key => $signature) {
            $phan_signatures_lc[\strtolower($key)] = $signature;
        }
        return $phan_signatures_lc;
    }
    /**
     * @return ?array<mixed,string>
     * @throws InvalidArgumentException
     */
    public function parseFunctionLikeSignature(string $method_name)
    {
        if (isset($this->aliases[$method_name])) {
            $method_name = $this->aliases[$method_name];
        }
        if (stripos($method_name, '::') !== false) {
            $parts = \explode('::', $method_name) ?: [];
            if (\count($parts) !== 2) {
                throw new InvalidArgumentException("Wrong number of parts in $method_name");
            }

            return $this->parseMethodSignature($parts[0], $parts[1]);
        }
        return $this->parseFunctionSignature($method_name);
    }

    /** @return ?array<mixed,string> */
    abstract public function parseMethodSignature(string $class, string $method);

    /** @return ?array<mixed,string> */
    abstract public function parseFunctionSignature(string $function_name);

    /**
     * @param string $msg @phan-unused-param
     * @return void
     */
    protected static function debug(string $msg)
    {
        // uncomment the below line to see debug output
        // fwrite(STDERR, $msg);
    }

    /**
     * @return void
     */
    protected static function info(string $msg)
    {
        // comment out the below line to hide debug output
        fwrite(STDERR, $msg);
    }

    /**
     * @param array<string,mixed> &$phan_signatures
     * @return void
     */
    public static function sortSignatureMap(array &$phan_signatures)
    {
        uksort($phan_signatures, static function (string $a, string $b) : int {
            $a = strtolower(str_replace("'", "\x0", $a));
            $b = strtolower(str_replace("'", "\x0", $b));
            return $a <=> $b;
        });
    }

    /** @return array<string,array<int|string,string>> */
    public static function readSignatureMap() : array
    {
        return require(ORIGINAL_SIGNATURE_PATH);
    }

    /**
     * @throws RuntimeException if the file could not be read
     */
    public static function readSignatureHeader() : string
    {
        return self::readArrayFileHeader(ORIGINAL_SIGNATURE_PATH);
    }

    /**
     * @throws RuntimeException if the file could not be read
     */
    public static function readFunctionDocumentationHeader() : string
    {
        return self::readArrayFileHeader(ORIGINAL_FUNCTION_DOCUMENTATION_PATH);
    }

    /**
     * @throws RuntimeException if the file could not be read
     */
    public static function readConstantDocumentationHeader() : string
    {
        return self::readArrayFileHeader(ORIGINAL_CONSTANT_DOCUMENTATION_PATH);
    }

    /**
     * @throws RuntimeException if the file could not be read
     */
    public static function readClassDocumentationHeader() : string
    {
        return self::readArrayFileHeader(ORIGINAL_CLASS_DOCUMENTATION_PATH);
    }

    /**
     * @throws RuntimeException if the file could not be read
     */
    private static function readArrayFileHeader(string $path) : string
    {
        $fin = fopen($path, 'r');
        if (!$fin) {
            throw new RuntimeException("Failed to start reading header\n");
        }
        $header = '';
        try {
            while (($line = fgets($fin)) !== false) {
                if (preg_match('/^\s*return\b/', $line)) {
                    return $header;
                }
                $header .= $line;
            }
        } finally {
            fclose($fin);
        }
        return '';
    }

    /**
     * @suppress PhanUnreferencedPublicMethod
     * @return array<string,string>
     */
    public static function readFunctionDocumentationMap() : array
    {
        return require(ORIGINAL_FUNCTION_DOCUMENTATION_PATH);
    }

    /**
     * @suppress PhanUnreferencedPublicMethod
     * @return array<string,string>
     */
    public static function readConstantDocumentationMap() : array
    {
        return require(ORIGINAL_CONSTANT_DOCUMENTATION_PATH);
    }

    /**
     * @suppress PhanUnreferencedPublicMethod
     * @return array<string,string>
     */
    public static function readClassDocumentationMap() : array
    {
        return require(ORIGINAL_CLASS_DOCUMENTATION_PATH);
    }

    /**
     * @param array<string,array<int|string,string>> $phan_signatures
     * @return void
     */
    public static function saveSignatureMap(string $new_signature_path, array $phan_signatures, bool $include_header = true)
    {
        $contents = static::serializeSignatures($phan_signatures);
        if ($include_header) {
            $contents = static::readSignatureHeader() . $contents;
        }
        file_put_contents($new_signature_path, $contents);
    }

    /**
     * @param array<string,array<int|string,string>> $signatures
     * @return string
     */
    public static function serializeSignatures(array $signatures) : string
    {
        $parts = "return [\n";
        foreach ($signatures as $function_like_name => $arguments) {
            $parts .= static::encodeSingleSignature($function_like_name, $arguments);
        }
        $parts .= "];\n";
        return $parts;
    }

    /**
     * @param array<string,string> $phan_documentation
     * @return void
     */
    public static function saveFunctionDocumentationMap(string $new_documentation_path, array $phan_documentation, bool $include_header = true)
    {
        $contents = static::serializeDocumentation($phan_documentation);
        if ($include_header) {
            $contents = static::readFunctionDocumentationHeader() . $contents;
        }
        file_put_contents($new_documentation_path, $contents);
    }

    /**
     * @param array<string,string> $phan_documentation
     * @return void
     */
    public static function saveConstantDocumentationMap(string $new_documentation_path, array $phan_documentation, bool $include_header = true)
    {
        $contents = static::serializeDocumentation($phan_documentation);
        if ($include_header) {
            $contents = static::readConstantDocumentationHeader() . $contents;
        }
        file_put_contents($new_documentation_path, $contents);
    }

    /**
     * @param array<string,string> $phan_documentation
     * @return void
     */
    public static function saveClassDocumentationMap(string $new_documentation_path, array $phan_documentation, bool $include_header = true)
    {
        $contents = static::serializeDocumentation($phan_documentation);
        if ($include_header) {
            $contents = static::readClassDocumentationHeader() . $contents;
        }
        file_put_contents($new_documentation_path, $contents);
    }

    /**
     * @param array<string,string> $signatures
     * @return string
     */
    public static function serializeDocumentation(array $signatures) : string
    {
        $parts = "return [\n";
        foreach ($signatures as $function_like_name => $arguments) {
            $parts .= static::encodeSingleDocumentation($function_like_name, $arguments);
        }
        $parts .= "];\n";
        return $parts;
    }

    /** @param int|string|float $scalar */
    protected static function encodeScalar($scalar) : string
    {
        if (is_string($scalar)) {
            return "'" . addcslashes($scalar, "'") . "'";
        }
        return (string)$scalar;
    }

    /**
     * @param array<mixed,string> $arguments the return type and parameter signatures

     */
    public static function encodeSingleSignature(string $function_like_name, array $arguments) : string
    {
        $result = static::encodeScalar($function_like_name) . ' => ';
        $result .= static::encodeSignatureArguments($arguments);
        $result .= ",\n";
        return $result;
    }

    /**
     * Encodes a single line with documentation of internal functions/methods

     */
    public static function encodeSingleDocumentation(string $function_like_name, string $description) : string
    {
        $result = static::encodeScalar($function_like_name) . ' => ';
        $result .= static::encodeScalar($description);
        $result .= ",\n";
        return $result;
    }

    /**
     * @param array<mixed,string> $arguments
     */
    public static function encodeSignatureArguments(array $arguments) : string
    {
        $result = '[';
        foreach ($arguments as $key => $arg) {
            if ($key !== 0) {
                $result .= ', ' . static::encodeScalar($key) . '=>';
            }
            $result .= static::encodeScalar($arg);
        }
        $result .= "]";
        return $result;
    }
}
