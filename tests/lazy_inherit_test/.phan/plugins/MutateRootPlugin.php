<?php

declare(strict_types=1);

use Phan\CodeBase;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Method;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeClassCapability;
use Phan\PluginV3\AnalyzeMethodCapability;
use Phan\PluginV3\AnalyzeMethodSkipsInheritedCopiesCapability;

/**
 * A test plugin of the lazy_inherited_methods fixture suite (src/014_guard.php).
 *
 * When the class \G014First is analyzed (in the class phase, after it imported the methods of \V014Base and before
 * \G014Second does), this initializes the scope of the root method \V014Base::configure.
 * That changes the state of the root between two imports: the copy that \G014Second inherits must be cloned from
 * the changed root (LazyInheritedMethods captures a new template), and the one of \G014First from the root before
 * the change.
 */
final class MutateRootPlugin extends PluginV3 implements
    AnalyzeClassCapability,
    AnalyzeMethodCapability,
    AnalyzeMethodSkipsInheritedCopiesCapability
{
    public function analyzeClass(CodeBase $code_base, Clazz $class): void
    {
        if ($class->getFQSEN()->__toString() !== '\G014First') {
            return;
        }
        $fqsen = FullyQualifiedMethodName::fromFullyQualifiedString('\V014Base::configure');
        if ($code_base->hasMethodWithFQSEN($fqsen)) {
            $code_base->getMethodByFQSEN($fqsen)->ensureScopeInitialized($code_base);
        }
    }

    public function analyzeMethod(CodeBase $code_base, Method $method): void
    {
        if ($method->getFQSEN() !== $method->getDefiningFQSEN()) {
            // An inherited copy (AnalyzeMethodSkipsInheritedCopiesCapability)
            return;
        }
        if ($method->getName() === 'reportedByMutateRootPlugin') {
            self::emitIssue(
                $code_base,
                $method->getContext(),
                'MutateRootPluginMethod',
                'Analyzed the declaration of {METHOD}',
                [(string)$method->getFQSEN()]
            );
        }
    }
}

return new MutateRootPlugin();
