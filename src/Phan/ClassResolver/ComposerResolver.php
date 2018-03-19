<?php declare(strict_types=1);
namespace Phan\ClassResolver;

use Composer\Autoload\ClassLoader;
use Phan\Language\FQSEN\FullyQualifiedClassName;

/**
 * The ComposerResolver uses the Composer autoloader to attempt to resolve files from a class name.
 */
class ComposerResolver implements ClassResolverInterface
{
    /**
     * @var \Composer\Autoload\ClassLoader
     */
    private $composer_class_loader;

    /**
     * ClassResolver constructor.
     *
     * @param ClassLoader $composer_class_loader
     */
    public function __construct(ClassLoader $composer_class_loader)
    {
        $this->composer_class_loader = $composer_class_loader;
    }

    /**
     * @inheritDoc
     */
    public function fileForClass(FullyQualifiedClassName $fqsen): string
    {
        $file_path = $this->composer_class_loader->findFile($fqsen->getNamespacedName());

        if (!$file_path) {
            return '';
        }

        return $file_path;
    }
}
