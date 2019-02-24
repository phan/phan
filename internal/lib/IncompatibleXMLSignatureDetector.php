<?php declare(strict_types=1);

use Phan\Config;
use Phan\Memoize;

require_once __DIR__ . '/IncompatibleSignatureDetectorBase.php';
require_once __DIR__ . '/IncompatibleStubsSignatureDetector.php';

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
    use Memoize;

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
        $this->aliases = $this->parseAliases();
    }

    /**
     * Parse information about which global functions are aliases of other global functions.
     * @return array<string,string> maps alias name to original name
     */
    private function parseAliases() : array
    {
        $file_name = $this->doc_base_directory . '/en/appendices/aliases.xml';
        $xml = $this->getSimpleXMLForFile($file_name);
        if (!$xml) {
            return [];
        }
        $result = [];
        foreach ($xml->children()[2]->table->tgroup->tbody->children() as $row) {
            $entry = $row->entry;
            $alias = (string)$entry[0];
            $original = (string)$entry[1]->function;
            if (!$original || !$alias) {
                // E.g. an alias to a method such as ociassignelem
                continue;
            }
            $result[$alias] = $original;
        }
        return $result;
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

    /** @return array<string,array<string,string>> a set of unique file names */
    private function getFilesForFunctionNameList() : array
    {
        return $this->memoize(__METHOD__, /** @return array<string,array<string,string>> */ function () {
            $files_for_function_name_list = [];
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
                        $files_for_function_name_list[strtolower($real_function_name)][$function_doc_fullpath] = $function_doc_fullpath;
                    }
                }
            }
            return $files_for_function_name_list;
        });
    }

    /**
     * @return array<string,string>
     */
    private function scandirForXML(string $dir) : array
    {
        $result = [];
        foreach (static::scandir($dir) as $basename) {
            if (substr($basename, -4) !== '.xml') {
                continue;
            }
            $full_path = "$dir/$basename";
            if (is_file($full_path)) {
                $normalized_name = strtolower(str_replace('-', '_', (string)substr($basename, 0, -4)));
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
        // TODO: Extract inheritance from classname.xml

        // TODO: Just parse every single xml file and extract the class name (including namespace)
        // from the XML itself instead of guessing based on heuristics.
        foreach (static::scandir($this->reference_directory) as $subpath) {
            $this->populateFoldersRecursively($subpath);
        }
        return $this->folders_for_class_name_list;
    }

    private function populateFoldersRecursively(string $subpath)
    {
        $extension_directory = "$this->reference_directory/$subpath";
        foreach (static::scandir($extension_directory) as $subsubpath) {
            $class_subpath = "$extension_directory/$subsubpath";
            if (is_dir($class_subpath) && strtolower($subsubpath) !== 'functions') {
                $class_name = $this->parseClassName("$subpath/$subsubpath");
                $normalized_class_name = strtolower(str_replace(['-', '/'], ['_', '\\'], $class_name));
                // echo "Reading $class_subpath $normalized_class_name\n";
                $this->folders_for_class_name_list[$normalized_class_name][$class_subpath] = $class_subpath;
                $this->populateFoldersRecursively("$subpath/$subsubpath");
            }
        }
    }

    /**
     * @return array<string,SimpleXMLElement>
     */
    private function getClassXMLFiles()
    {
        return $this->memoize(__METHOD__, /** @return array<string,SimpleXMLElement> */ function () : array {
            $remaining_folders = [
                $this->reference_directory,
                $this->doc_base_directory . '/en/language/predefined'
            ];
            $result = [];
            while (count($remaining_folders) > 0) {
                $folder = array_pop($remaining_folders);
                if (!$folder) {
                    // impossible
                    break;
                }
                foreach (static::scandir($folder) as $basename) {
                    if ($basename === 'functions') {
                        continue;
                    }
                    $path = "$folder/$basename";
                    if (is_dir($path)) {
                        $remaining_folders[] = $path;
                        continue;
                    }
                    if (!preg_match('/\.xml$/', $basename)) {
                        continue;
                    }
                    $contents = (string)$this->fileGetContents($path);
                    if (!preg_match('/<phpdoc:classref|<classsynopsis/', $contents)) {
                        continue;
                    }
                    $xml = $this->getSimpleXMLForFileContents($contents, $path);
                    if (!$xml) {
                        continue;
                    }
                    $result[$path] = $xml;
                }
            }
            return $result;
        });
    }

    /**
     * @return Generator<string>
     */
    private function getPossibleFilesInReferenceDirectory(string $folder_in_reference_directory)
    {
        $file = $this->reference_directory . '/' . $folder_in_reference_directory . '.xml';
        yield $file;
        $parts = explode('/', $folder_in_reference_directory, 2);
        $alternate_basename = str_replace('/', '.', $parts[1]) . '.xml';
        $alternate_file = $this->reference_directory . '/' . $parts[0] . '/' . $alternate_basename;
        if ($alternate_file !== $file) {
            yield $alternate_file;
        }
        $alternate_file_2 = $this->reference_directory . '/' . $parts[0] . '/' . str_replace('_', '-', $alternate_basename);
        if ($alternate_file_2 !== $alternate_file) {
            yield $alternate_file_2;
        }
        yield $this->reference_directory . '/' . $parts[0] . '/' . $parts[0] . '.' . str_replace('_', '-', $alternate_basename);
    }

    private function parseClassName(string $folder_in_reference_directory) : string
    {
        foreach ($this->getPossibleFilesInReferenceDirectory($folder_in_reference_directory) as $file_in_reference_directory) {
            //echo "Looking for $file_in_reference_directory\n";
            if (file_exists($file_in_reference_directory)) {
                //echo "Found $file_in_reference_directory\n";
                $xml = $this->getSimpleXMLForFile($file_in_reference_directory);
                if (!$xml) {
                    continue;
                }
                $results = $xml->xpath('//a:classsynopsisinfo/a:ooclass/a:classname');
                if (is_array($results) && count($results) === 1) {
                    echo "Returning $results[0]\n";
                    return (string)$results[0];
                }
                break;
            }
        }
        return preg_replace('@^[^/]*/@', '', $folder_in_reference_directory);
    }

    /**
     * Execute one of several possible commands to update Phan's stub files.
     *
     * @return void
     */
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
                break;
            case 'update-descriptions-svn':
                if (count($argv) !== 3) {
                    fwrite(STDERR, "Invalid argument count, update-descriptions-svn expects 1 argument\n");
                    static::printUsageAndExit();
                }
                // TODO: Add a way to exclude /tests/
                $detector = new IncompatibleXMLSignatureDetector($argv[2]);
                $detector->selfTest();
                $detector->updatePHPDocSummaries();
                break;
            case 'update-descriptions-stubs':
                if (count($argv) !== 3) {
                    fwrite(STDERR, "Invalid argument count, update-descriptions-stubs expects 1 argument\n");
                    static::printUsageAndExit();
                }
                $detector = new IncompatibleStubsSignatureDetector($argv[2]);
                $detector->selfTest();
                $detector->updatePHPDocSummaries();
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

    /**
     * Sort the signature map and save to to $filename.sorted
     * @return void
     */
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
        $this->expectFunctionLikeSignaturesMatch('disk_free_space', ['float', 'directory' => 'string']);
        $this->expectFunctionLikeSignaturesMatch('EvWatcher::feed', ['void', 'revents' => 'int']);
        $this->expectFunctionLikeSignaturesMatch('intdiv', ['int', 'dividend' => 'int', 'divisor' => 'int']);
        $this->expectFunctionLikeSignaturesMatch('ArrayIterator::seek', ['void', 'position' => 'int']);
        $this->expectFunctionLikeSignaturesMatch('mb_chr', ['string', 'cp' => 'int', 'encoding=' => 'string']);
    }

    /**
     * @param array<int|string,string> $expected
     */
    private function expectFunctionLikeSignaturesMatch(string $function_name, array $expected)
    {
        $actual = $this->parseFunctionLikeSignature($function_name);
        if ($expected !== $actual) {
            printf("Extraction failed for %s\nExpected: %s\nActual:   %s\n", $function_name, json_encode($expected) ?: 'invalid', json_encode($actual) ?: 'invalid');
            exit(1);
        }
    }

    /**
     * @return ?SimpleXMLElement the simple xml for the global function $function_name
     */
    public function getSimpleXMLForFunctionSignature(string $function_name)
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
        if (!is_string($signature_file)) {
            static::info("invalid signature file\n");
            return null;
        }
        // Not sure if there's a good way of using an external entity file in PHP.
        return $this->getSimpleXMLForFile($signature_file);
    }

    /**
     * @return ?array<mixed,string>
     */
    public function parseFunctionSignature(string $function_name)
    {
        $xml = $this->getSimpleXMLForFunctionSignature($function_name);
        if ($xml === null) {
            return null;
        }
        return $this->parseFunctionLikeSignatureForXML($function_name, $xml);
    }

    /**
     * Returns the SimpleXMLElement with the documentation of each method in $class_name.
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
        if (!is_string($class_folder)) {
            static::info("Invalid array for $class_name\n");
            return null;
        }
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
            if (strpos($case_sensitive_method_name, '::') === false) {
                static::info("Unexpected format of method name '$case_sensitive_method_name', expected something like '$class_name::$method_name_lc'\n");
                continue;
            }
            if ($class_name_lc) {
                $case_sensitive_method_name = $class_name_lc . '::' . explode('::', $case_sensitive_method_name, 2)[1];
            }
            $result[$case_sensitive_method_name] = $xml;
        }
        return $result;
    }

    /**
     * @return ?array<mixed,string>
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
        $xml = $this->getSimpleXMLForFile($method_filename);
        if ($xml === null) {
            return null;
        }
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

    /** @return string|false */
    private function fileGetContents(string $file_path)
    {
        return $this->memoize(__METHOD__ . ':' . $file_path, /** @return string|false */ static function () use ($file_path) {
            return file_get_contents($file_path);
        });
    }

    /** @return ?SimpleXMLElement */
    private function getSimpleXMLForFileUncached(string $file_path)
    {
        $signature_file_contents = $this->fileGetContents($file_path);
        if (!is_string($signature_file_contents)) {
            static::debug("Could not read '$file_path'\n");
            return null;
        }
        return $this->getSimpleXMLForFileContents($signature_file_contents, $file_path);
    }

    /**
     * @return ?SimpleXMLElement
     */
    private function getSimpleXMLForFileContents(string $signature_file_contents, string $file_path)
    {
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
        $name = $xml->xpath('/a:refentry/a:refnamediv/a:refname') ?: [];
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
        $name = $xml->xpath('/a:refentry/a:refnamediv/a:refname') ?: [];
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
     * @return ?array<mixed,string>
     */
    private function parseFunctionLikeSignatureForXML(string $function_name, $xml)
    {
        if (!$xml) {
            return null;
        }
        // echo $contents->asXML();
        // $function_description = $contents->xpath('/refentity/refsect1[role=description]/methodsynopsis');
        $function_description_list = $xml->xpath('/a:refentry/a:refsect1[@role="description"]/a:methodsynopsis') ?: [];
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
        $type = ltrim($type, '\\');
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
            $abs_path = "$this->doc_base_directory/$sub_path";
            $contents = file_get_contents($abs_path);
            if (!$contents) {
                throw new AssertionError("Failed to load $abs_path");
            }
            foreach (explode("\n", $contents) as $line) {
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
        return preg_replace_callback('/&([-a-zA-Z_.0-9]+);/', static function ($matches) use ($entities) : string {
            $entity_name = $matches[1];
            if (isset($entities[strtolower($entity_name)])) {
                return "BEGINENTITY{$entity_name}ENDENTITY";
            }
            if (preg_match('/^reference\./', $entity_name)) {
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
        return $this->memoize(__METHOD__, /** @return array<string,array<int|string,string>> */ function () : array {
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
     * Normalize the extracted XML and convert it to markdown/HTML.
     * @return ?string - Returns null if this is just a placeholder
     */
    private static function convertXMLToMarkdown(string $text)
    {
        $result = preg_replace_callback(
            '/BEGINENTITY(\S*)ENDENTITY/',
            /**
             * @param array{0:string,1:string} $matches
             */
            static function (array $matches) : string {
                $text = $matches[1];
                $text = trim(preg_replace("/\\s+/m", " ", $text));
                if (strtolower($text) === 'alias') {
                    return 'Alias of';
                }
                return "<code>$text</code>";
            },
            trim($text)
        );
        switch (strtolower($result)) {
            case '':
                return null;
        }

        $result = preg_replace('@<code>([^<>`]+)</code>@', '`\1`', $result);

        return $result;
    }

    /**
     * Normalize the extracted XML and convert it to markdown/HTML for a summary.
     * @return ?string - Returns null if this is just a placeholder
     */
    private static function normalizeExtractedXMLSummary(string $text)
    {
        $result = preg_replace('/\s+/m', ' ', self::convertXMLToMarkdown($text) ?? '');
        if (!$result) {
            return null;
        }
        if (strtolower($result) === 'description') {
            return null;
        }

        return $result;
    }

    /**
     * Returns short phpdoc summaries of function and method signatures
     *
     * @return array<string,string>
     * @override
     */
    protected function getAvailableMethodPHPDocSummaries() : array
    {
        return $this->memoize(__METHOD__, /** @return array<string,string> */ function () : array {
            $method_name_map = [];
            $maybe_add_refpurpose = static function (string $name, SimpleXMLElement $xml) use (&$method_name_map) {
                $refpurpose = $xml->xpath('//a:refentry/a:refnamediv/a:refpurpose');
                if (is_array($refpurpose) && count($refpurpose) === 1) {
                    $refpurpose = $refpurpose[0];
                    if ($refpurpose instanceof SimpleXMLElement) {
                        // @phan-suppress-next-line PhanPartialTypeMismatchArgumentInternal
                        $refpurpose = strip_tags($refpurpose->asXML());
                    }
                    // echo "Looking at $method_name: refpurpose = $refpurpose\n";
                    if (!$refpurpose) {
                        return;
                    }
                    $refpurpose = self::normalizeExtractedXMLSummary(trim($refpurpose));
                    if (!$refpurpose) {
                        return;
                    }
                    $method_name_map[$name] = $refpurpose;
                }
            };
            foreach ($this->getFoldersForClassNameList() as $class_name => $unused_folder) {
                foreach ($this->getMethodsForClassName($class_name) ?? [] as $method_name => $xml) {
                    $maybe_add_refpurpose($method_name, $xml);
                }
            }
            foreach ($this->getFilesForFunctionNameList() as $function_name => $unused_files) {
                $xml = $this->getSimpleXMLForFunctionSignature($function_name);
                // echo "Looking at $function_name\n";
                if (!$xml) {
                    // echo "Could not find xml\n";
                    continue;
                }
                $maybe_add_refpurpose($function_name, $xml);
            }
            self::sortSignatureMap($method_name_map);
            return $method_name_map;
        });
    }

    /**
     * @return array<string,string>
     * @override
     */
    protected function getAvailableConstantPHPDocSummaries() : array
    {
        return $this->memoize(__METHOD__, /** @return array<string,string> */ function () : array {
            $constant_name_map = [];
            foreach ($this->getFilesForConstants() as $xml_file_name) {
                $xml = $this->getSimpleXMLForFile($xml_file_name);
                if (!$xml) {
                    fwrite(STDERR, "Failed to parse XML for $xml_file_name\n");
                    continue;
                }
                $constants_entries = $xml->xpath('//a:variablelist/a:varlistentry');
                // var_export($constants_entries);
                if (!is_array($constants_entries)) {
                    continue;
                }
                $constant_name_map += self::extractConstantEntries($constants_entries);
            }
            self::sortSignatureMap($constant_name_map);
            return $constant_name_map;
        });
    }

    /**
     * @return array<string,string>
     * @override
     */
    protected function getAvailableClassPHPDocSummaries() : array
    {
        return $this->memoize(__METHOD__, /** @return array<string,string> */ function () : array {
            $class_name_map = [];
            foreach ($this->getClassXMLFiles() as $xml) {
                $class_name = $xml->xpath('//a:classsynopsis/a:ooclass/a:classname');
                if (!is_array($class_name) || count($class_name) !== 1) {
                    continue;
                }
                $class_name = (string)$class_name[0];
                // $class_name = (string)$xml->titleabbrev;
                if (!$class_name) {
                    continue;
                }
                $class_description_entries = $xml->partintro->section[0];
                if (count($class_description_entries) === 0) {
                    continue;
                }
                $paragraphs = iterator_to_array($class_description_entries->para, false);
                $text = self::extractDescriptionFromParagraphElements($paragraphs);
                if (!$text) {
                    continue;
                }
                $class_name_map[$class_name] = $text;
                // echo "$class_name: $text\n";
            }
            self::sortSignatureMap($class_name_map);
            return $class_name_map;
        });
    }

    /**
     * @return ?string
     */
    private static function convertXMLElementToMarkdown(SimpleXMLElement $element)
    {
        $xml = (string)$element->asXML();
        if (strpos($xml, '<xref') !== false) {
            $xml = preg_replace('@<xref linkend="([^"]+)"\s*/>@', 'the PHP manual\'s section on \1', $xml);
        }
        // TODO: Change this to use tidy if adding the extra dependency won't cause issues.
        //
        // Convert <literal> to <code>, etc, to use regular HTML.
        //
        // TODO: Reuse more of the code from
        $xml = preg_replace('@<(/?)(literal|classname|interfacename|property|methodname|constant|function|type)\s*>@i', '<\1code>', $xml);
        $xml = preg_replace('@<(/?)(emphasis)\s*>@i', '*', $xml);
        // echo $xml . "\n";

        $xml = strip_tags($xml, '<code><em>');
        $xml = preg_replace('/\s+/m', ' ', $xml);

        return self::convertXMLToMarkdown($xml);
    }

    /**
     * @param array<int,SimpleXMLElement> $constants_entries
     * @return array<string,string>
     */
    private static function extractConstantEntries(array $constants_entries) : array
    {
        $result = [];
        foreach ($constants_entries as $entry) {
            $entry->registerXPathNamespace('a', 'http://docbook.org/ns/docbook');
            $name = $entry->term->constant;
            // var_export($entry);
            // echo "The extracted names are:\n";
            // var_export($name);
            if ($name->count() !== 1) {
                fwrite(STDERR, "Failed to parse $entry\n");
                continue;
            }
            $name = (string)$name[0];
            $description_paragraphs = $entry->listitem->simpara;
            if (count($description_paragraphs) === 0) {
                // fwrite(STDERR, "Failed to extract description for $entry\n");
                continue;
            }
            $description_paragraphs = iterator_to_array($description_paragraphs, false);
            $text = self::extractDescriptionFromParagraphElements($description_paragraphs);
            if (!$text) {
                continue;
            }
            $result[$name] = $text;
            echo "$name: $result[$name]\n";
        }
        self::sortSignatureMap($result);
        return $result;
    }

    /**
     * Returns a markdown/HTML description for $description_paragraphs
     *
     * @param array<int,SimpleXMLElement> $description_paragraphs
     * @return ?string
     */
    private static function extractDescriptionFromParagraphElements(array $description_paragraphs)
    {
        $lines = [];
        foreach ($description_paragraphs as $element) {
            // TODO: Do a better job than strip_tags
            $line = self::convertXMLElementToMarkdown($element);
            // fwrite(STDERR, "Extracted $line from $element\n");
            if ($line) {
                $lines[] = preg_replace('/\s+/m', ' ', $line);
            }
        }
        if (!$lines) {
            return null;
        }
        return implode("\n\n", $lines);
    }

    /**
     * @return array<string,string> maps extension name to constants.xml
     */
    private function getFilesForConstants() : array
    {
        return $this->memoize(__METHOD__, /** @return array<string,string> */ function () : array {
            $constants_files = [];
            $reserved_constants_file = $this->doc_base_directory . '/en/appendices/reserved.constants.core.xml';
            if (!file_exists($reserved_constants_file)) {
                throw new RuntimeException("Failed to load $reserved_constants_file");
            }
            $constants_files['reserved.core'] = $reserved_constants_file;

            foreach (static::scandir($this->reference_directory) as $extension) {
                $subpath = $this->reference_directory . "/$extension";
                $constants_file_name = "$subpath/constants.xml";
                if (file_exists($constants_file_name)) {
                    $constants_files[$extension] = $constants_file_name;
                }
            }
            return $constants_files;
        });
    }

    /**
     * @return array<string,array<int|string,string>>
     * @override
     */
    protected function getAvailableMethodSignatures() : array
    {

        return $this->memoize(__METHOD__, /** @return array<string,array<int|string,string>> */ function () : array {
            $method_name_map = [];
            foreach ($this->getFoldersForClassNameList() as $class_name => $unused_folder) {
                foreach ($this->getMethodsForClassName($class_name) ?? [] as $method_name => $xml) {
                    $signature_from_doc = $this->parseFunctionLikeSignatureForXML($method_name, $xml);
                    if ($signature_from_doc === null) {
                        continue;
                    }
                    // echo "For $class_name found $method_name\n";
                    $method_name_map[$method_name] = $signature_from_doc;
                }
            }
            return $method_name_map;
        });
    }

    /**
     * Same as scandir, but ignores hidden files
     * @return array<int,string>
     */
    private static function scandir(string $directory) : array
    {
        if (!is_dir($directory)) {
            return [];
        }
        $result = [];

        foreach (scandir($directory) as $subpath) {
            if ($subpath[0] !== '.') {
                $result[] = $subpath;
            }
        }
        return $result;
    }
}
