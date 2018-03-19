<?php declare(strict_types=1);
namespace Phan\ClassResolver;

use Phan\Language\FQSEN\FullyQualifiedClassName;

/**
 * ClassResolverInterface defines the public interface for class resolvers that are used during the evaluation phase
 * to dynamically resolve a class to a file and an analyze classes as they are discovered. The intention is to speed
 * up the analysis phase so as not to have to analyze the entire project and only analyze classes that are directly
 * depended upon.
 */
interface ClassResolverInterface
{
    /**
     * Resolve the file path for a FullyQualifiedClassName
     *
     * @param FullyQualifiedClassName $fqsen
     * @return string
     */
    public function fileForClass(FullyQualifiedClassName $fqsen): string;
}
