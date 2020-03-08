<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal;

use Phan\CLI;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Flags;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Library\FileCache;
use Phan\Plugin\Internal\CtagsPlugin\CtagsEntry;
use Phan\Plugin\Internal\CtagsPlugin\CtagsEntrySet;
use Phan\PluginV3;
use Phan\PluginV3\FinalizeProcessCapability;

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
        if (!$class->isAnonymous()) {
            // Generate a ctags entry for named classes (no possible tag for `new class extends ...`)
            $entries->add(new CtagsEntry(
                $class->getName(),
                $class->getContext(),
                CtagsEntry::KIND_CLASS,
                CtagsEntry::generateScopeLabelForNamespace($class->getContext()->getNamespace())
            ));
        }
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

        foreach ($class->getPropertyMap($code_base) as $property) {
            if ($property->isDynamicProperty()) {
                continue;
            }
            $property_fqsen = $property->getFQSEN();
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

return new CtagsPlugin();
