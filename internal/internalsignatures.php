#!/usr/bin/env php
<?php declare(strict_types=1);

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
class IncompatibleSignatureDetector {
    /** @var string */
    private $reference_directory;

    /** @var string */
    private $doc_base_directory;

    private function __construct(string $dir) {
        if (!is_dir($dir)) {
            echo "Could not find '$dir'\n";
            self::printUsageAndExit();
        }
        $en_reference_dir = "$dir/en/reference";
        if (!is_dir($en_reference_dir)) {
            echo "Could not find subdirectory '$en_reference_dir'\n";
            self::printUsageAndExit();
        }
        $this->reference_directory = realpath($en_reference_dir);
        $this->base_directory = realpath($dir);
    }

    private static function printUsageAndExit(int $exit_code = 1) {
        global $argv;
        echo "Usage: $argv[0] path/to/phpdoc_svn_dir\n";
        echo "  phpdoc_svn_dir can be checked out via 'svn checkout https://svn.php.net/repository/phpdoc/modules/doc-en phpdoc-en' (subversion must be installed)\n";
        echo "  see http://doc.php.net/tutorial/structure.php\n";
        exit($exit_code);
    }


    /** @var array<string,array<string,string>> a set of unique file names */
    private $files_for_function_name_list;


    private function getFilesForFunctionNameList() {
        if ($this->files_for_function_name_list === null) {
            $this->files_for_function_name_list = $this->populateFilesForFunctionNameList();
        }
        return $this->files_for_function_name_list;
    }

    private function populateFilesForFunctionNameList() {
        $this->files_for_function_name_list = [];
        $reference_directory = $this->reference_directory;
        foreach (self::scandir($reference_directory) as $subpath) {
            $functions_subsubdir = "$reference_directory/$subpath/functions";
            if (is_dir($functions_subsubdir)) {
                foreach (self::scandir($functions_subsubdir) as $function_doc_file) {
                    if (substr($function_doc_file, -4) !== '.xml') {
                        continue;
                    }
                    $function_doc_fullpath = "$functions_subsubdir/$function_doc_file";
                    if (is_file($function_doc_fullpath)) {
                        $function_name = strtolower(str_replace('-', '_', substr($function_doc_file, 0, -4)));
                        $this->files_for_function_name_list[$function_name][$function_doc_fullpath] = $function_doc_fullpath;
                    }
                }
            }
        }
        return $this->files_for_function_name_list;
    }

    /** @var array<string,array<string,string>>|null */
    private $folders_for_class_name_list;

    private function getFoldersForClassNameList() {
        if ($this->folders_for_class_name_list === null) {
            $this->folders_for_class_name_list = $this->populateFoldersForClassNameList();
        }
        return $this->folders_for_class_name_list;
    }

    private function populateFoldersForClassNameList() {
        $this->folders_for_class_name_list = [];
        $reference_directory = $this->reference_directory;
        // TODO: Extract inheritance from classname.xml
        foreach (self::scandir($reference_directory) as $subpath) {
            $extension_directory = "$reference_directory/$subpath";
            foreach (self::scandir($extension_directory) as $subsubpath) {
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
    public static function main() {
        error_reporting(E_ALL);
        global $argv;
        if (\count($argv) !== 2) {
            // TODO: CLI flags
            self::printUsageAndExit();
        }
        $detector = new IncompatibleSignatureDetector($argv[1]);
        $detector->selfTest();

        $detector->updateFunctionSignatures();
    }

    private function selfTest() {
        $this->expectFunctionLikeSignaturesMatch('strlen', ['int', 'string' => 'string']);
        $this->expectFunctionLikeSignaturesMatch('ob_clean', ['void']);
        $this->expectFunctionLikeSignaturesMatch('intdiv', ['int', 'dividend' => 'int', 'divisor' => 'int']);
        $this->expectFunctionLikeSignaturesMatch('ArrayIterator::seek', ['void', 'position' => 'int']);
    }

    private function expectFunctionLikeSignaturesMatch(string $function_name, array $expected) {
        $actual = $this->parseFunctionLikeSignature($function_name);
        if ($expected !== $actual) {
            printf("Extraction failed for %s\nExpected: %s\nActual:   %s\n", $function_name, json_encode($expected), json_encode($actual));
            exit(1);
        }
    }

    public function parseFunctionLikeSignature(string $method_name) {
        if (stripos($method_name, '::') !== false) {
            $parts = \explode('::', $method_name);
            \assert(\count($parts) === 2, new Error("Too many parts"));

            return $this->parseMethodSignature($parts[0], $parts[1]);
        }
        return $this->parseFunctionSignature($method_name);
    }

    /**
     * @return ?array
     */
    public function parseFunctionSignature(string $function_name) : ?array {
        $function_name_lc = strtolower($function_name);
        $function_name_file_map = $this->getFilesForFunctionNameList();
        $function_signature_files = $function_name_file_map[$function_name_lc] ?? null;
        if ($function_signature_files === null) {
            self::debug("Could not find $function_name\n");
            return null;
        }
        if (count($function_signature_files) !== 1) {
            self::debug("Expected only one signature for $function_name\n");
            return null;
        }
        $signature_file = \reset($function_signature_files);
        $signature_file_contents = file_get_contents($signature_file);
        if (!is_string($signature_file_contents)) {
            self::debug("Could not read '$signature_file'\n");
            return null;
        }
        // Not sure if there's a good way of using an external entity file in PHP.
        $signature_file_contents = $this->normalizeEntityFile($signature_file_contents);
        return $this->parseFunctionLikeSignatureForContents($function_name, $signature_file_contents);
    }

    /**
     * @return ?array
     */
    public function parseMethodSignature(string $class_name, string $method_name) : ?array {
        $class_name_lc = strtolower($class_name);
        $method_name_lc = strtolower($method_name);
        $class_name_file_map = $this->getFoldersForClassNameList();
        $class_name_files = $class_name_file_map[$class_name_lc] ?? null;
        if ($class_name_files === null) {
            self::debug("Could not find class directory for $class_name\n");
            return null;
        }
        if (count($class_name_files) !== 1) {
            self::debug("Expected only one class implementation for $class_name\n");
            return null;
        }
        $class_folder = \reset($class_name_files);
        $method_filename = "$class_folder/" . str_replace('_', '-', $method_name_lc) . ".xml";
        if (!is_file($method_filename)) {
            self::debug("Could not find $method_filename\n");
            // TODO: What about inherited methods?
            return null;
        }
        $signature_file_contents = file_get_contents($method_filename);
        if (!is_string($signature_file_contents)) {
            self::debug("Could not read '$signature_file'\n");
            return null;
        }
        // Not sure if there's a good way of using an external entity file in PHP.
        $signature_file_contents = $this->normalizeEntityFile($signature_file_contents);
        return $this->parseFunctionLikeSignatureForContents("{$class_name}::{$method_name}", $signature_file_contents);
    }

    private function parseFunctionLikeSignatureForContents(string $function_name, string $signature_file_contents) : ?array {
        // echo $signature_file_contents . "\n";
        try {
            $contents = new SimpleXMLElement($signature_file_contents, LIBXML_ERR_NONE);
        } catch (Exception $e) {
            self::info("Failed to parse signature for '$function_name' : " . $e->getMessage() . "\n");
            return null;
        }
        $contents->registerXPathNamespace('a', 'http://docbook.org/ns/docbook');
        // echo $contents->asXML();
        // $function_description = $contents->xpath('/refentity/refsect1[role=description]/methodsynopsis');
        $function_description_list = $contents->xpath('/a:refentry/a:refsect1[@role="description"]/a:methodsynopsis');
        if (count($function_description_list) !== 1) {
            self::debug("Too many descriptions for '$function_name'\n");
            return null;
        }
        $function_description = $function_description_list[0];
        $function_return_type = $function_description->type;
        $return_type = self::toTypeString($function_description->type);
        $params = $this->extractMethodParams($function_description->methodparam);
        $result = array_merge([$return_type], $params);
        return $result;
    }

    /**
     * @return array<int,string>
     */
    private function extractMethodParams(SimpleXMLElement $param) {
        if ($param->count() === 0) {
            return [];
        }
        if ($param->count() === 1) {
            $param = [$param];
        }
        $result = [];
        foreach ($param as $part) {
            $result[(string)$part->parameter] = self::toTypeString($part->type);
        }
        return $result;
    }

    private static function toTypeString($type) : string {
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
     * @var array<string,string>
     */
    private $known_entities = null;

    private function computeKnownEntities() {
        $this->known_entities = [];
        foreach (['doc-base/entities/global.ent', 'en/contributors.ent', 'en/extensions.ent', 'en/language-defs.ent', 'en/language-snippets.ent'] as $sub_path) {
            foreach (explode("\n", file_get_contents("$this->base_directory/$sub_path")) as $line) {
                if (preg_match('/^<!ENTITY\s+(\S+)/', $line, $matches)) {
                    $entity_name = $matches[1];
                    $this->known_entities[strtolower($entity_name)] = true;
                }
            }
        }
        return $this->known_entities;
    }

    private function getKnownEntities() {
        if (!is_array($this->known_entities)) {
            $this->known_entities = $this->computeKnownEntities();
        }
        return $this->known_entities;
    }

    private function normalizeEntityFile(string $contents) : string {
        $entities = $this->getKnownEntities();
        return preg_replace_callback('/&([-a-zA-Z_.0-9]+);/', function($matches) use ($entities) {
            $entity_name = $matches[1];
            if (isset($entities[strtolower($entity_name)])) {
                return "BEGINENTITY{$entity_name}ENDENTITY";
            }
            // echo "Could not find entity $entity_name in $matches[0]\n";
            return $matches[0];
        }, $contents);
    }

    /** @return void */
    public function updateFunctionSignatures() {
        $original_signature_path = __DIR__ . '/../src/Phan/Language/Internal/FunctionSignatureMap.php';
        $phan_signatures = require($original_signature_path);
        $new_signatures = [];
        foreach ($phan_signatures as $method_name => $arguments) {
            if (stripos($method_name, "'") !== false || isset($phan_signatures["$method_name'1"])) {
                // Don't update functions/methods with alternate
                $new_signatures[$method_name] = $arguments;
                continue;
            }
            $new_signatures[$method_name] = self::updateSignature($method_name, $arguments);
        }
        $new_signature_path = $original_signature_path . '.new';
        self::info("Saving modified function signatures to $new_signature_path (updating param and return types)\n");
        file_put_contents($new_signature_path, self::serializeSignatures($new_signatures));
    }

    private static function encodeScalar($scalar) {
        if (is_string($scalar)) {
            return "'" . addcslashes($scalar, "'") . "'";
        }
        return $scalar;
    }

    public static function encodeSingleSignature(string $function_like_name, array $arguments) : string
    {
        $result = self::encodeScalar($function_like_name) . ' => [';
        foreach($arguments as $key => $arg) {
            if ($key !== 0) {
                $result .= ', ' . self::encodeScalar($key) . '=>';
            }
            $result .= self::encodeScalar($arg);
        }
        $result .= "],\n";
        return $result;
    }
    public static function serializeSignatures(array $signatures) {
        $parts = ["return [\n"];
        foreach ($signatures as $function_like_name => $arguments) {
            $parts[] = self::encodeSingleSignature($function_like_name, $arguments);
        }
        $parts[] = "];\n";
        return implode('', $parts);
    }

    /**
     * @return array|null
     */
    private function updateSignature(string $function_like_name, array $arguments_from_phan) {
        $return_type = $arguments_from_phan[0];
        $arguments_from_svn = null;
        if ($return_type === '') {
            $arguments_from_svn = $arguments_from_svn ?? $this->parseFunctionLikeSignature($function_like_name);
            if (is_null($arguments_from_svn)) {
                return $arguments_from_phan;
            }
            $svn_return_type = $arguments_from_svn[0] ?? '';
            if ($svn_return_type !== '') {
                self::debug("A better Phan return type for $function_like_name is " . $svn_return_type . "\n");
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
                self::debug("A better Phan param type for $function_like_name (for param #$param_index called \$$param_name) is $param_from_svn\n");
                $arguments_from_phan[$param_name] = $param_from_svn;
            }
        }
        // TODO: Update param types
        return $arguments_from_phan;
    }

    // Same as scandir, but ignores hidden files
    private static function scandir(string $directory) : array {
        $result = [];
        foreach (scandir($directory) as $subpath) {
            if ($subpath[0] !== '.') {
                $result[] = $subpath;
            }
        }
        return $result;
    }

    private static function debug(string $msg) {
        // uncomment the below line to see debug output
        // fwrite(STDERR, $msg);
    }

    private static function info(string $msg) {
        // comment out the below line to hide debug output
        fwrite(STDERR, $msg);
    }
}

IncompatibleSignatureDetector::main();

