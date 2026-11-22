<?php

declare(strict_types=1);

use Phan\CodeBase;
use Phan\Language\Element\AddressableElementInterface;
use Phan\Language\Element\Comment\CustomAnnotation;
use Phan\PluginV3;
use Phan\PluginV3\CustomAnnotationCapability;

/**
 * A plugin that declares an annotation of Phan itself: Phan fails to load the plugins (see test.sh).
 */
final class BuiltinNameTestPlugin extends PluginV3 implements CustomAnnotationCapability
{
    public function getCustomAnnotations(): array
    {
        return ['phan-test-mark' => '', 'phan-param' => 'An annotation of Phan'];
    }

    public function handleCustomAnnotation(CodeBase $code_base, AddressableElementInterface $element, CustomAnnotation $annotation): void
    {
    }
}

return new BuiltinNameTestPlugin();
