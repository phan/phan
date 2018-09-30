#!/usr/bin/env php
<?php declare(strict_types=1);
// @phan-file-suppress PhanNativePHPSyntaxCheckPlugin

use Phan\Analysis;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Element\Func;
use Phan\Language\Element\Method;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Memoize;
use Phan\Output\Collector\BufferingCollector;
use Phan\Phan;

require dirname(__DIR__) . '/vendor/autoload.php';

define('ORIGINAL_SIGNATURE_PATH', dirname(__DIR__) . '/src/Phan/Language/Internal/FunctionSignatureMap.php');

/**
 * Implementations of this can be used to check Phan's function signature map.
 *
 * They do the following:
 *
 * - Load signatures from an external source
 * - Compare the signatures against Phan's to report incomplete or inaccurate signatures of Phan itself (or the external signature)
 *
 * TODO: could extend this to properties (the use of properties in extensions is rare).
 *
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */
abstract class IncompatibleSignatureDetectorBase
{
    use Memoize;

    const FUNCTIONLIKE_BLACKLIST = '@(^___PHPSTORM_HELPERS)|PS_UNRESERVE_PREFIX@';

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
     * @return array|null
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
        if (stripos($method_name, '::') !== false) {
            $parts = \explode('::', $method_name);
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
        uksort($phan_signatures, function (string $a, string $b) : int {
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

    public static function encodeSingleSignature(string $function_like_name, array $arguments) : string
    {
        $result = static::encodeScalar($function_like_name) . ' => [';
        foreach ($arguments as $key => $arg) {
            if ($key !== 0) {
                $result .= ', ' . static::encodeScalar($key) . '=>';
            }
            $result .= static::encodeScalar($arg);
        }
        $result .= "],\n";
        return $result;
    }
}


/**
 * A utility to read php.net's xml documentation for functions, methods,
 * and use that to update Phan's internal signature map (Currently just return types of functions and methods)
 * Author: Tyson Andre
 *
 * Usage:
 *      php internal/internalsignatures.php path/to/phpdoc-en
 *
 * TODO: Refactor this class into multiple classes
 * TODO: This has a bit of code in common with sanitycheck.php, refactor?
 * phpdoc-en can be downloaded via 'svn checkout https://svn.php.net/repository/phpdoc/modules/doc-en phpdoc-en'
 */
class IncompatibleXMLSignatureDetector extends IncompatibleSignatureDetectorBase
{
    /** @var string the directory for english PHP element references */
    private $reference_directory;

    /** @var string the base directory of the svn phpdoc repo */
    private $doc_base_directory;

    private function __construct(string $dir)
    {
        if (!is_dir($dir)) {
            echo "Could not find '$dir'\n";
            static::printUsageAndExit();
        }
        Config::setValue('ignore_undeclared_functions_with_known_signatures', false);

        $en_reference_dir = "$dir/en/reference";
        if (!is_dir($en_reference_dir)) {
            fwrite(STDERR, "Could not find subdirectory '$en_reference_dir'\n");
            static::printUsageAndExit();
        }
        $this->reference_directory = self::realpath($en_reference_dir);
        $this->doc_base_directory = self::realpath($dir);
    }

    /**
     * @throws RuntimeException if the real path could not be determined
     */
    private static function realpath(string $dir) : string
    {
        $realpath = realpath($dir);
        if (!is_string($realpath)) {
            fwrite(STDERR, "Could not find realpath of '$dir'\n");
            static::printUsageAndExit();
            throw new RuntimeException("unreachable");
        }
        return $realpath;
    }

    /** @var array<string,array<string,string>> a set of unique file names */
    private $files_for_function_name_list;


    /** @return array<string,array<string,string>> a set of unique file names */
    private function getFilesForFunctionNameList() : array
    {
        if ($this->files_for_function_name_list === null) {
            $this->files_for_function_name_list = $this->populateFilesForFunctionNameList();
        }
        return $this->files_for_function_name_list;
    }

    private function scandirForXML(string $dir) : array
    {
        $result = [];
        foreach (static::scandir($dir) as $basename) {
            if (substr($basename, -4) !== '.xml') {
                continue;
            }
            $full_path = "$dir/$basename";
            if (is_file($full_path)) {
                $normalized_name = strtolower(str_replace('-', '_', substr($basename, 0, -4)));
                $result[$full_path] = $normalized_name;
            }
        }
        return $result;
    }

    // These aren't built in functions, but they're documented like them.
    const INVALID_FUNCTION_NAMES = [
        // keywords.
        'array',
        'list',
        // Deprecated method that has an effect if defined by PHP code.
        '__autoload',
    ];

    /**
     * @return array<string,array<string,string>>
     */
    private function populateFilesForFunctionNameList() : array
    {
        $this->files_for_function_name_list = [];
        $reference_directory = $this->reference_directory;
        foreach (static::scandir($reference_directory) as $subpath) {
            $functions_subsubdir = "$reference_directory/$subpath/functions";
            if (is_dir($functions_subsubdir)) {
                foreach (static::scandirForXML($functions_subsubdir) as $function_doc_fullpath => $unused_function_name) {
                    $xml = $this->getSimpleXMLForFile($function_doc_fullpath);
                    if (!$xml) {
                        continue;
                    }
                    $real_function_name = $this->getFunctionNameFromXML($xml);
                    if (!$real_function_name) {
                        continue;
                    }
                    if (in_array($real_function_name, static::INVALID_FUNCTION_NAMES, true)) {
                        continue;
                    }
                    $this->files_for_function_name_list[strtolower($real_function_name)][$function_doc_fullpath] = $function_doc_fullpath;
                }
            }
        }
        return $this->files_for_function_name_list;
    }

    /**
     * @var array<string,array<string,string>>
     * Maps class names to a unique set of folders [$class_name => [$folder_name => $folder_name]]
     */
    private $folders_for_class_name_list;

    /**
     * @return array<string,array<string,string>>
     */
    private function getFoldersForClassNameList() : array
    {
        if ($this->folders_for_class_name_list === null) {
            $this->folders_for_class_name_list = $this->populateFoldersForClassNameList();
        }
        return $this->folders_for_class_name_list;
    }

    /**
     * @return array<string,array<string,string>>
     */
    private function populateFoldersForClassNameList()
    {
        $this->folders_for_class_name_list = [];
        $reference_directory = $this->reference_directory;
        // TODO: Extract inheritance from classname.xml

        // TODO: Just parse every single xml file and extract the class name (including namespace)
        // from the XML itself instead of guessing based on heuristics.
        foreach (static::scandir($reference_directory) as $subpath) {
            $extension_directory = "$reference_directory/$subpath";
            foreach (static::scandir($extension_directory) as $subsubpath) {
                $class_subpath = "$extension_directory/$subsubpath";
                $class_name = strtolower($subsubpath);
                if (is_dir($class_subpath) && $class_name !== 'functions') {
                    $this->folders_for_class_name_list[strtolower(str_replace('-', '_', $class_name))][$class_subpath] = $class_subpath;
                }
            }
        }
        return $this->folders_for_class_name_list;
    }

    /** @return void */
    public static function main()
    {
        error_reporting(E_ALL);
        ini_set('memory_limit', '2G');
        global $argv;
        if (\count($argv) < 2) {
            // TODO: CLI flags
            static::printUsageAndExit();
        }
        $command = $argv[1];
        switch ($command) {
            case 'sort':
                if (count($argv) !== 2) {
                    fwrite(STDERR, "Invalid argument count, sort expects no arguments\n");
                    static::printUsageAndExit();
                }
                static::sortSignatureMapInPlace();
                break;
            case 'update-svn':
                if (count($argv) !== 3) {
                    fwrite(STDERR, "Invalid argument count, update-svn expects 1 argument\n");
                    static::printUsageAndExit();
                }
                $detector = new IncompatibleXMLSignatureDetector($argv[2]);
                $detector->selfTest();

                $detector->addMissingFunctionLikeSignatures();
                $detector->updateFunctionSignatures();
                // TODO: Sort .php.extra_signatures and .php.new
                break;
            case 'update-stubs':
                if (count($argv) !== 3) {
                    fwrite(STDERR, "Invalid argument count, update-stubs expects 1 argument\n");
                    static::printUsageAndExit();
                }
                // TODO: Add a way to exclude /tests/
                $detector = new IncompatibleStubsSignatureDetector($argv[2]);
                $detector->selfTest();
                $detector->addMissingFunctionLikeSignatures();
                $detector->updateFunctionSignatures();
                // TODO: Sort .php.extra_signatures and .php.new

                break;
            case 'help':
            case '--help':
            case '-h':
                static::printUsageAndExit(0);
                return;  // unreachable
            default:
                fwrite(STDERR, "Invalid command '$command'\n");
                static::printUsageAndExit(1);
        }
    }

    /** @return void */
    public static function sortSignatureMapInPlace()
    {
        $phan_signatures = static::readSignatureMap();
        static::sortSignatureMap($phan_signatures);
        $sorted_phan_signatures_path = ORIGINAL_SIGNATURE_PATH . '.sorted';
        static::info("Saving sorted Phan signatures to '$sorted_phan_signatures_path'\n");
        static::saveSignatureMap($sorted_phan_signatures_path, $phan_signatures);
    }

    /**
     * @suppress PhanPluginMixedKeyNoKey
     */
    private function selfTest()
    {
        $this->expectFunctionLikeSignaturesMatch('strlen', ['int', 'string' => 'string']);
        $this->expectFunctionLikeSignaturesMatch('ob_clean', ['void']);
        $this->expectFunctionLikeSignaturesMatch('intdiv', ['int', 'dividend' => 'int', 'divisor' => 'int']);
        $this->expectFunctionLikeSignaturesMatch('ArrayIterator::seek', ['void', 'position' => 'int']);
        $this->expectFunctionLikeSignaturesMatch('mb_chr', ['string', 'cp' => 'int', 'encoding=' => 'string']);
    }

    private function expectFunctionLikeSignaturesMatch(string $function_name, array $expected)
    {
        $actual = $this->parseFunctionLikeSignature($function_name);
        if ($expected !== $actual) {
            printf("Extraction failed for %s\nExpected: %s\nActual:   %s\n", $function_name, json_encode($expected), json_encode($actual));
            exit(1);
        }
    }

    /**
     * @return ?array
     */
    public function parseFunctionSignature(string $function_name)
    {
        $function_name_lc = strtolower($function_name);
        $function_name_file_map = $this->getFilesForFunctionNameList();
        $function_signature_files = $function_name_file_map[$function_name_lc] ?? null;
        if ($function_signature_files === null) {
            static::debug("Could not find $function_name\n");
            return null;
        }
        if (count($function_signature_files) !== 1) {
            static::debug("Expected only one signature for $function_name\n");
            return null;
        }
        $signature_file = \reset($function_signature_files);
        $signature_file_contents = file_get_contents($signature_file);
        if (!is_string($signature_file_contents)) {
            static::debug("Could not read '$signature_file'\n");
            return null;
        }
        // Not sure if there's a good way of using an external entity file in PHP.
        $xml = $this->getSimpleXMLForFile($signature_file);
        return $this->parseFunctionLikeSignatureForXML($function_name, $xml);
    }

    /**
     * @return ?array<string,SimpleXMLElement>
     */
    public function getMethodsForClassName(string $class_name)
    {
        $class_name_lc = strtolower($class_name);
        $class_name_file_map = $this->getFoldersForClassNameList();
        $class_name_files = $class_name_file_map[$class_name_lc] ?? null;
        if ($class_name_files === null) {
            static::debug("Could not find class directory for $class_name\n");
            return null;
        }
        if (count($class_name_files) !== 1) {
            static::debug("Expected only one class implementation for $class_name\n");
            return null;
        }
        $class_folder = \reset($class_name_files);
        $result = [];
        foreach (static::scandirForXML($class_folder) as $method_xml_path => $method_name_lc) {
            $xml = $this->getSimpleXMLForFile($method_xml_path);
            if (!$xml) {
                static::info("Failed to parse information for $class_name::$method_name_lc from '$method_xml_path'\n");
                continue;
            }
            $case_sensitive_method_name = $this->getMethodNameFromXML($xml);
            if (!$case_sensitive_method_name) {
                static::info("Failed to parse method name for '$class_name::$method_name_lc' in '$method_xml_path'\n");
                continue;
            }
            if (stripos($case_sensitive_method_name, '::') === false) {
                static::info("Unexpected format of method name '$case_sensitive_method_name', expected something like '$class_name::$method_name_lc'\n");
                continue;
            }
            $result[$case_sensitive_method_name] = $xml;
        }
        return $result;
    }

    /**
     * @return ?array
     */
    public function parseMethodSignature(string $class_name, string $method_name)
    {
        $class_name_lc = strtolower($class_name);
        $method_name_lc = strtolower($method_name);
        $class_name_file_map = $this->getFoldersForClassNameList();
        $class_name_files = $class_name_file_map[$class_name_lc] ?? null;
        if ($class_name_files === null) {
            static::debug("Could not find class directory for $class_name\n");
            return null;
        }
        if (count($class_name_files) !== 1) {
            static::debug("Expected only one class implementation for $class_name\n");
            return null;
        }
        $class_folder = \reset($class_name_files);
        $method_filename = "$class_folder/" . str_replace('_', '-', $method_name_lc) . ".xml";
        if (!is_file($method_filename)) {
            static::debug("Could not find $method_filename\n");
            // TODO: What about inherited methods?
            return null;
        }
        $xml = $this->getSimpleXMLForFile($method_filename);
        return $this->parseFunctionLikeSignatureForXML("{$class_name}::{$method_name}", $xml);
    }

    /** @var array<string,?SimpleXMLElement> maps file paths to cached parsed XML elements */
    private $simple_xml_cache = [];

    /** @return ?SimpleXMLElement */
    private function getSimpleXMLForFile(string $file_path)
    {
        if (array_key_exists($file_path, $this->simple_xml_cache)) {
            return $this->simple_xml_cache[$file_path];
        }
        return $this->simple_xml_cache[$file_path] = $this->getSimpleXMLForFileUncached($file_path);
    }

    /** @return ?SimpleXMLElement */
    private function getSimpleXMLForFileUncached(string $file_path)
    {
        $signature_file_contents = file_get_contents($file_path);
        if (!is_string($signature_file_contents)) {
            static::debug("Could not read '$file_path'\n");
            return null;
        }
        // Not sure if there's a good way of using an external entity file in PHP.
        $signature_file_contents = $this->normalizeEntityFile($signature_file_contents);
        // echo $signature_file_contents . "\n";
        try {
            $result = new SimpleXMLElement($signature_file_contents, LIBXML_ERR_NONE);
        } catch (Exception $e) {
            static::info("Failed to parse signature from file '$file_path' : " . $e->getMessage() . "\n");
            return null;
        }
        $result->registerXPathNamespace('a', 'http://docbook.org/ns/docbook');
        return $result;
    }

    /** @return ?string */
    private function getFunctionNameFromXML(SimpleXMLElement $xml)
    {
        $name = $xml->xpath('/a:refentry/a:refnamediv/a:refname');
        if (count($name) === 0) {
            return null;
        }
        $valid_names = [];
        foreach ($name as $potential_name) {
            $potential_name = (string)$potential_name;
            if (strpos($potential_name, '$') === false) {
                $valid_names[] = $potential_name;
            }
        }
        // E.g. CurlFile::__construct and curl_file_create
        if (count($valid_names) === 1) {
            return $valid_names[0];
        }
        return null;
    }

    /** @return ?string */
    private function getMethodNameFromXML(SimpleXMLElement $xml)
    {
        $name = $xml->xpath('/a:refentry/a:refnamediv/a:refname');
        if (count($name) === 0) {
            return null;
        }
        $valid_names = [];
        foreach ($name as $potential_name) {
            $potential_name = (string)$potential_name;
            if (strpos($potential_name, '::') !== false && strpos($potential_name, '$') === false) {
                $valid_names[] = $potential_name;
            }
        }
        // E.g. CurlFile::__construct and curl_file_create
        if (count($valid_names) === 1) {
            return $valid_names[0];
        }
        return null;
    }

    /**
     * @param string $function_name
     * @param ?SimpleXMLElement $xml
     * @return ?array
     */
    private function parseFunctionLikeSignatureForXML(string $function_name, $xml)
    {
        if (!$xml) {
            return null;
        }
        // echo $contents->asXML();
        // $function_description = $contents->xpath('/refentity/refsect1[role=description]/methodsynopsis');
        $function_description_list = $xml->xpath('/a:refentry/a:refsect1[@role="description"]/a:methodsynopsis');
        if (count($function_description_list) !== 1) {
            static::debug("Too many descriptions for '$function_name'\n");
            return null;
        }
        $function_description = $function_description_list[0];
        // $function_return_type = $function_description->type;
        $return_type = static::toTypeString($function_description->type);
        $params = $this->extractMethodParams($function_description->methodparam);
        $result = array_merge([$return_type], $params);
        return $result;
    }

    /**
     * @return array<string,string>
     */
    private function extractMethodParams(SimpleXMLElement $param)
    {
        if ($param->count() === 0) {
            return [];
        }
        $result = [];
        $i = 0;
        foreach ($param as $part) {
            $i++;
            $param_name = (string)$part->parameter;
            if (!$param_name) {
                $param_name = 'arg' . $i;
            }

            if (((string)$part->attributes()['choice'] ?? '') === 'opt') {
                $param_name .= '=';
            }

            $result[$param_name] = self::toTypeString($part->type);
        }
        return $result;
    }

    /** @param string|int|float $type */
    private static function toTypeString($type) : string
    {
        // TODO: Validate that Phan can parse these?
        $type = (string)$type;
        if (strcasecmp($type, 'scalar') === 0) {
            return 'int|string|float|bool';
        }
        if (strcasecmp($type, 'iterator') === 0) {
            return 'iterator';
        }
        return $type;
    }


    /**
     * @var array<string,true> a list of known expandable PHPDoc entities.
     * We expand these into stub strings before parsing XML to avoid being overwhelmed with PHPDoc notices from SimpleXMLElement
     */
    private $known_entities = null;

    /**
     * @return array<string,true>
     */
    private function computeKnownEntities() : array
    {
        $this->known_entities = [];
        foreach (['doc-base/entities/global.ent', 'en/contributors.ent', 'en/extensions.ent', 'en/language-defs.ent', 'en/language-snippets.ent'] as $sub_path) {
            foreach (explode("\n", file_get_contents("$this->doc_base_directory/$sub_path")) as $line) {
                if (preg_match('/^<!ENTITY\s+(\S+)/', $line, $matches)) {
                    $entity_name = $matches[1];
                    $this->known_entities[strtolower($entity_name)] = true;
                }
            }
        }
        return $this->known_entities;
    }

    /**
     * @return array<string,true>
     */
    private function getKnownEntities()
    {
        if (!is_array($this->known_entities)) {
            $this->known_entities = $this->computeKnownEntities();
        }
        return $this->known_entities;
    }

    private function normalizeEntityFile(string $contents) : string
    {
        $entities = $this->getKnownEntities();
        /**
         * @param array<int,string> $matches
         */
        return preg_replace_callback('/&([-a-zA-Z_.0-9]+);/', function ($matches) use ($entities) : string {
            $entity_name = $matches[1];
            if (isset($entities[strtolower($entity_name)])) {
                return "BEGINENTITY{$entity_name}ENDENTITY";
            }
            // echo "Could not find entity $entity_name in $matches[0]\n";
            return $matches[0];
        }, $contents);
    }

    /**
     * @return array<string,array<int|string,string>>
     * @override
     */
    protected function getAvailableGlobalFunctionSignatures() : array
    {
        return $this->memoize(__METHOD__, function () : array {
            $function_name_map = [];
            foreach ($this->getFilesForFunctionNameList() as $function_name => $unused_files) {
                $signature_from_doc = $this->parseFunctionSignature($function_name);
                if ($signature_from_doc === null) {
                    continue;
                }
                $function_name_map[$function_name] = $signature_from_doc;
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
            $method_name_map = [];
            foreach ($this->getFoldersForClassNameList() as $class_name => $unused_folder) {
                foreach ($this->getMethodsForClassName($class_name) ?? [] as $method_name => $xml) {
                    $signature_from_doc = $this->parseFunctionLikeSignatureForXML($method_name, $xml);
                    if ($signature_from_doc === null) {
                        continue;
                    }
                    $method_name_map[$method_name] = $signature_from_doc;
                }
            }
            return $method_name_map;
        });
    }

    // Same as scandir, but ignores hidden files
    private static function scandir(string $directory) : array
    {
        $result = [];
        foreach (scandir($directory) as $subpath) {
            if ($subpath[0] !== '.') {
                $result[] = $subpath;
            }
        }
        return $result;
    }
}

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
        $this->code_base = require(__DIR__ . '/../src/codebase.php');
    }

    /**
     * @return void
     * @suppress PhanPluginMixedKeyNoKey
     */
    public function selfTest()
    {
        $failures = 0;
        $failures += $this->expectFunctionLikeSignaturesMatch('strlen', ['int', 'string' => 'string']);
        // $this->expectFunctionLikeSignaturesMatch('ob_clean', ['void']);
        $failures += $this->expectFunctionLikeSignaturesMatch('intdiv', ['int', 'numerator' => 'int', 'divisor' => 'int']);
        $failures += $this->expectFunctionLikeSignaturesMatch('ArrayIterator::seek', ['void', 'position' => 'int']);
        $failures += $this->expectFunctionLikeSignaturesMatch('Redis::hGet', ['string', 'key' => 'string', 'hashKey' => 'string']);
        if ($failures > 0) {
            exit(1);
        }
    }

    private function expectFunctionLikeSignaturesMatch(string $function_name, array $expected) : int
    {
        $actual = $this->parseFunctionLikeSignature($function_name);
        if ($expected !== $actual) {
            fprintf(STDERR, "Extraction failed for %s\nExpected: %s\nActual:   %s\n", $function_name, json_encode($expected), json_encode($actual));
            return 1;
        }
        return 0;
    }

    /** @var bool has this initialized and parsed all of the stubs yet? */
    private $initialized = false;

    private function getFileList() : array
    {
        $iterator = new \CallbackFilterIterator(
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $this->directory,
                    \RecursiveDirectoryIterator::FOLLOW_SYMLINKS
                )
            ),
            function (\SplFileInfo $file_info) : bool {
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

        return array_keys(iterator_to_array($iterator));
    }

    /**
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
        echo "Found $method_fqsen at " . $method->getFileRef()->getFile() . "\n";

        $method->ensureScopeInitialized($code_base);
        return $method->toFunctionSignatureArray();
    }

    /**
     * @return ?array
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

IncompatibleXMLSignatureDetector::main();
