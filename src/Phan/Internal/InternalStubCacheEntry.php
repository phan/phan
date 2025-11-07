<?php

/** @phan-file-suppress PhanAccessMethodInternal */

declare(strict_types=1);

namespace Phan\Internal;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\ClassConstant;
use Phan\Language\Element\Func;
use Phan\Language\Element\GlobalConstant;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;

/**
 * Tracks the results of parsing a PHP internal stub file so that it can be restored without reparsing.
 *
 * @internal
 */
final class InternalStubCacheEntry
{
    /**
     * @param list<Clazz> $classes
     * @param list<Func> $functions
     * @param list<GlobalConstant> $global_constants
     * @param list<string> $file_level_suppressions
     * @param array<string,array{methods:list<Method>,properties:list<Property>,constants:list<ClassConstant>}> $class_members
     */
    public function __construct(
        private string $hash,
        private Context $context,
        private array $classes,
        private array $functions,
        private array $global_constants,
        private array $file_level_suppressions,
        private array $class_members
    ) {
    }

    /**
     * Checks whether the cached entry matches the provided hash.
     */
    public function matchesHash(string $hash): bool
    {
        return $this->hash === $hash;
    }

    /**
     * Replays cached definitions into the provided code base.
     */
    public function applyToCodeBase(CodeBase $code_base): Context
    {
        foreach ($this->classes as $class) {
            $code_base->addClass(clone $class);
        }
        foreach ($this->functions as $function) {
            $cloned_function = clone $function;
            $code_base->addFunction($cloned_function);
            $fqsen = $cloned_function->getFQSEN();
            if ($fqsen instanceof FullyQualifiedFunctionName) {
                $code_base->markFunctionFullyLoaded($fqsen);
            }
        }
        foreach ($this->global_constants as $constant) {
            $code_base->addGlobalConstant(clone $constant);
        }
        foreach ($this->class_members as $members) {
            foreach ($members['constants'] as $constant) {
                $code_base->addClassConstant(clone $constant);
            }
            foreach ($members['properties'] as $property) {
                $code_base->addProperty(clone $property);
            }
            foreach ($members['methods'] as $method) {
                $code_base->addMethod(clone $method);
            }
        }
        $file = $this->context->getFile();
        foreach ($this->file_level_suppressions as $issue_type) {
            $code_base->addFileLevelSuppression($file, $issue_type);
        }
        $context_clone = clone $this->context;
        $code_base->addParsedNamespaceMap(
            $context_clone->getFile(),
            $context_clone->getNamespace(),
            $context_clone->getNamespaceId(),
            $context_clone->getNamespaceMap()
        );
        return $context_clone;
    }

}
