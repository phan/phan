<?php declare(strict_types=1);
namespace Phan\ClassResolver;

use Phan\Language\FQSEN\FullyQualifiedClassName;

/**
 * The ReflectionResolver attempts to use PHPs autoload functionality and Reflection to dynamically find a classes file
 */
class ReflectionResolver implements ClassResolverInterface
{
    /**
     * @inheritDoc
     */
    public function fileForClass(FullyQualifiedClassName $fqsen): string
    {
        $class = $fqsen->getNamespacedName();
        if (!class_exists($class)) {
            return '';
        }

        $reflection = new \ReflectionClass($class);

        // Internal classes don't have a file
        if ($reflection->isInternal()) {
            return '';
        }

        $file = $reflection->getFileName();
        if (!$file || !file_exists($file)) {
            return '';
        }

        return $file;
    }
}
