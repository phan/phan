<?php declare(strict_types=1);
namespace Phan\ClassResolver;

use Composer\Autoload\ClassLoader;
use Phan\Language\FQSEN\FullyQualifiedClassName;

/**
 * The ComposerResolver uses the Composer autoloader to attempt to resolve files from a class name.
 * @suppress PhanUndeclaredTypeProperty
 * @suppress PhanUndeclaredTypeParameter
 */
class ComposerResolver implements ClassResolverInterface
{
    /**
     * @var \Composer\Autoload\ClassLoader
     */
    private $composer_class_loader;

    /**
     * Map of previously resolved classes
     *
     * @var array<string,string>
     */
    private $class_map = [];

    /**
     * ClassResolver constructor.
     *
     * @param ClassLoader $composer_class_loader A composer class loader instance
     */
    public function __construct(ClassLoader $composer_class_loader)
    {
        $this->composer_class_loader = $composer_class_loader;
    }

    /**
     * @inheritDoc
     * @suppress PhanUndeclaredClassMethod
     */
    public function fileForClass(FullyQualifiedClassName $fqsen): string
    {
        $class = $fqsen->getNamespacedName();
        if (isset($this->class_map[$class])) {
            return $this->class_map[$class];
        }

        $file = $this->composer_class_loader->findFile($class);

        if (!$file) {
            $file = '';
        }

        $this->class_map[$class] = $file;
        return $this->class_map[$class];
    }
}
