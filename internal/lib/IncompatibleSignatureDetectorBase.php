<?php
declare(strict_types=1);

use Phan\Memoize;

define('ORIGINAL_SIGNATURE_PATH', dirname(dirname(__DIR__)) . '/src/Phan/Language/Internal/FunctionSignatureMap.php');

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

    /** @var array<string,string> maps aliases to originals - only set for xml parser */
    protected $aliases = [];

    const FUNCTIONLIKE_BLACKLIST = '@' .
        '(^___PHPSTORM_HELPERS)|PS_UNRESERVE_PREFIX|' .
        '(^(ereg|expression|getsession|hrtime_|imageps|mssql_|mysql_|split|sql_regcase|sybase|xmldiff_))|' .
        '(^closure_)|' .  // Phan's representation of a closure
        '\.' .  // a literal `.`
        '@';

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

EOT;
        fwrite(STDERR, $msg);
        exit($exit_code);
    }

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
     * @return array
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
     * @return ?array
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

    /** @return ?array */
    abstract public function parseMethodSignature(string $class, string $method);

    /** @return ?array */
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
     * @param array<string,array> &$phan_signatures
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
        $fin = fopen(ORIGINAL_SIGNATURE_PATH, 'r');
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

    /** @param int|string|float $scalar */
    private static function encodeScalar($scalar) : string
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
