<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal;

use Exception;
use Phan\CLI;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Flags;
use Phan\Language\FileRef;
use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedClassElement;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Library\FileCache;
use Phan\PluginV3;
use Phan\PluginV3\FinalizeProcessCapability;

use function is_string;
use function strlen;

/**
 * This plugin generates a ctags file from the files parsed by Phan.
 *
 * NOTE: This is automatically loaded by phan based on CLI invocations.
 * Do not include it in a config.
 *
 * @see https://github.com/universal-ctags/ctags/blob/master/old-docs/website/FORMAT
 *
 * @phan-file-suppress PhanPluginWhitespaceTab
 */
class CtagsPlugin extends PluginV3 implements
    FinalizeProcessCapability
{
    /**
     * @var CtagsEntrySet
     */
    private static $entries;

    public function __construct()
    {
        self::$entries = new CtagsEntrySet();
        FileCache::raiseMaxCacheSize(100000);
    }

    public function finalizeProcess(CodeBase $code_base): void
    {
        if (Config::getValue('processes') > 1) {
            $tags_path = Config::projectPath('tags');
            CLI::printToStderr("Unable to generate tags file in '$tags_path' when running in multiple processes\n");
            return;
        }
        $this->dumpTagsFile($code_base);
    }

    /**
     * Dump a ctags file based on the current state of the codebase and any tags added since then
     */
    public function dumpTagsFile(CodeBase $code_base): void
    {
        $tags_path = Config::projectPath('tags');
        CLI::printToStderr("Saving a ctags file to '$tags_path'\n");
        self::generateEntriesForCodeBase($code_base, self::$entries);
        $contents = $this->generateAllBaselineContents(self::$entries);
        \file_put_contents($tags_path, $contents);
    }

    /**
     * Adds a set of unique ctags entries to $entries, for the definitions parsed in the codebase.
     *
     * If this is run after the analysis phase, then
     *
     * `$keyName = 'SOME_NAME'; define($keyName, $value)` is able to infer the line of define() as a definition of SOME_NAME.
     */
    public static function generateEntriesForCodeBase(CodeBase $code_base, CtagsEntrySet $entries): void
    {
        // TODO: Add ctags for internal stubs?
        foreach ($code_base->getUserDefinedClassMap() as $class_fqsen => $class) {
            self::addEntriesForClass($entries, $code_base, $class_fqsen, $class);
        }
        self::addEntriesForGlobalElements($entries, $code_base);
    }

    private static function addEntriesForClass(
        CtagsEntrySet $entries,
        CodeBase $code_base,
        FullyQualifiedClassName $class_fqsen,
        Clazz $class
    ): void {
        if ($class->isPHPInternal()) {
            return;
        }
        $entries->add(new CtagsEntry(
            $class->getName(),
            $class->getContext(),
            CtagsEntry::KIND_CLASS,
            CtagsEntry::generateScopeLabelForNamespace($class->getContext()->getNamespace())
        ));
        $label = CtagsEntry::generateScopeLabelForFQSEN($class_fqsen);

        foreach ($class->getConstantMap($code_base) as $const) {
            $const_fqsen = $const->getFQSEN();
            if ($const_fqsen !== $const->getDefiningFQSEN()) {
                continue;
            }
            $entries->add(new CtagsEntry(
                $const_fqsen->getName(),
                $const->getContext(),
                CtagsEntry::KIND_CONSTANT,
                $label
            ));
        }
        // TODO: Make this work for renamed methods/properties from traits, add tests
        foreach ($class->getMethodMap($code_base) as $method) {
            $method_fqsen = $method->getFQSEN();
            if ($method_fqsen !== $method->getDefiningFQSEN()) {
                continue;
            }
            $real_method_fqsen = $method->getRealDefiningFQSEN();
            if ($method_fqsen !== $real_method_fqsen && $method_fqsen->getName() === $real_method_fqsen->getName()) {
                // Only use the original method name from traits, except for `use Foo { originalMethod as aliasMethod; }`
                continue;
            }
            if ($method->getPhanFlags() & Flags::IS_FAKE_CONSTRUCTOR) {
                continue;
            }
            $entries->add(new CtagsEntry(
                $method_fqsen->getName(),
                $method->getContext(),
                CtagsEntry::KIND_FUNCTION,
                $label
            ));
        }

        foreach ($class->getPropertyMap($code_base) as $property_fqsen => $property) {
            if ($property->isDynamicProperty()) {
                continue;
            }
            if ($property_fqsen !== $property->getRealDefiningFQSEN()) {
                continue;
            }
            $entries->add(new CtagsEntry(
                $property->getFQSEN()->getName(),
                $property->getContext(),
                CtagsEntry::KIND_PROPERTY,
                $label
            ));
        }
    }

    /**
     * Generate ctags for global constants and global functions
     */
    private static function addEntriesForGlobalElements(
        CtagsEntrySet $entries,
        CodeBase $code_base
    ): void {
        foreach ($code_base->getFunctionMap() as $function_fqsen => $function) {
            if ($function->isClosure()) {
                continue;
            }
            $entries->add(new CtagsEntry(
                $function_fqsen->getName(),
                $function->getContext(),
                CtagsEntry::KIND_FUNCTION,
                CtagsEntry::generateScopeLabelForNamespace($function_fqsen->getNamespace())
            ));
        }

        foreach ($code_base->getGlobalConstantMap() as $const_fqsen => $const) {
            $const_name = $const_fqsen->getName();
            if (\strcasecmp($const_name, 'class') === 0) {
                // Don't bother outputting entries for the magic MyClass::class
                continue;
            }

            $entries->add(new CtagsEntry(
                $const_name,
                $const->getContext(),
                CtagsEntry::KIND_CONSTANT,
                CtagsEntry::generateScopeLabelForNamespace($const_fqsen->getNamespace())
            ));
        }
    }

    /**
     * Generate the ctags file contents with the provided ctags entries
     *
     * @param CtagsEntrySet $entries unique entries, pre-sorted
     */
    public function generateAllBaselineContents(CtagsEntrySet $entries): string
    {
        $version = CLI::PHAN_VERSION;
        $contents = <<<"EOT"
!_TAG_FILE_FORMAT	2	/extended format; --format=1 will not append ;" to lines/
!_TAG_FILE_SORTED	1	/0=unsorted, 1=sorted, 2=foldcase/
!_TAG_PROGRAM_AUTHOR	Tyson Andre
!_TAG_PROGRAM	phan	//
!_TAG_PROGRAM_URL	https://github.com/phan/phan	/official site/
!_TAG_PROGRAM_VERSION	$version	//

EOT;
        foreach ($entries->toArray() as $entry) {
            $contents .= "$entry\n";
        }
        return $contents;
    }
}

/**
 * Represents a sorted set of unique ctags entries for definitions of elements
 */
class CtagsEntrySet
{
    /** @var array<string, CtagsEntry> */
    private $entries = [];
    /**
     * @return array<string, CtagsEntry> a sorted map of sorted entries.
     * This sorts the entries when it is called.
     */
    public function toArray(): array
    {
        \uksort($this->entries, 'strcmp');
        return $this->entries;
    }

    /**
     * Record an occurrence of an element definition.
     */
    public function add(CtagsEntry $entry): void
    {
        if (!$entry->isValid()) {
            return;
        }
        $lookup = $entry->name . "\0" . $entry;
        $this->entries[$lookup] = $entry;
    }
}

/**
 * Represents a single ctags entry.
 *
 * @phan-read-only
 */
class CtagsEntry
{
    public const KIND_CLASS = 'c';
    /** "define" in ctags is also used for constants */
    public const KIND_CONSTANT = 'd';
    public const KIND_FUNCTION = 'f';
    public const KIND_PROPERTY = 'p';

    /** @var string the name of the tag */
    public $name;
    /** @var FileRef the file and line the tag referred to */
    public $context;
    /** @var string the kind of tag*/
    public $kind;
    /** @var ?string the scope, e.g. `function:some_function`, `namespace:MyNS\SubNS`, `class:SomeClass`, etc. */
    public $scope;
    /** @var string the code fragment to locate the tag */
    public $fragment;
    /** @var bool is this entry valid */
    private $is_valid = false;

    public function __construct(string $name, FileRef $context, string $kind, ?string $scope, string $fragment = null)
    {
        if ($context->isPHPInternal()) {
            return;
        }
        if (!\is_string($fragment)) {
            $path = Config::projectPath($context->getFile());
            try {
                $entry = FileCache::getOrReadEntry($path);
            } catch (Exception $e) {
                CLI::printToStderr("Failed to read $path: {$e->getMessage()}\n");
                return;
            }

            $fragment = $entry->getLines()[$context->getLineNumberStart()] ?? '';
        }
        if (\strlen($fragment) === 0) {
            CLI::printToStderr("Empty line for generating ctags for name=$name at $context kind=$kind\n");
            return;
        }
        $this->name = $name;
        $this->context = $context;
        $this->kind = $kind;
        $this->scope = $scope;
        $this->fragment = \rtrim($fragment);
        $this->is_valid = true;
    }

    /**
     * Is this ctags entry valid?
     */
    public function isValid(): bool
    {
        return $this->is_valid;
    }

    public function __toString()
    {
        /*
         * > The name of the "kind:" field can be omitted.  This is to reduce the size of
         * > the tags file by about 15%.  A program reading the tags file can recognize the
         * > "kind:" field by the missing ':'.
         */
        $ctags_path = Config::projectPath($this->context->getFile());
        $common = \sprintf(
            "%s\t%s\t%s\t%s\tline:%d",
            $this->name,
            $ctags_path,
            self::escapeFragment($this->fragment),
            $this->kind,
            $this->context->getLineNumberStart()
        );
        if (is_string($this->scope) && strlen($this->scope) > 0) {
            $common .= "\t$this->scope";
        }
        return $common;
    }

    /**
     * Escape a code fragment for the ctags file format.
     */
    public static function escapeFragment(string $fragment): string {
        $escaped = \str_replace(['\\', '/', "\0"], ['\\\\', '\/', '\\0'], $fragment);
        return '/^' . $escaped . '$/;"';
    }

    /**
     * Generate a scope label for the provided FQSEN
     */
    public static function generateScopeLabelForFQSEN(?FQSEN $fqsen): ?string
    {
        if ($fqsen instanceof FullyQualifiedClassName) {
            return 'class:' . \ltrim((string)$fqsen, '\\');
        } elseif ($fqsen instanceof FullyQualifiedClassElement) {
            return self::generateScopeLabelForFQSEN($fqsen->getFullyQualifiedClassName());
        }
        return null;
    }

    /**
     * Generate a scope label for the provided namespace.
     */
    public static function generateScopeLabelForNamespace(string $namespace): ?string
    {
        $namespace = \ltrim($namespace, "\\");
        if (strlen($namespace) > 0)  {
            return "namespace:$namespace";
        }
        return null;
    }
}

return new CtagsPlugin();
