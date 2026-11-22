<?php

declare(strict_types=1);

use Phan\CodeBase;
use Phan\Language\Element\AddressableElementInterface;
use Phan\Language\Element\Comment\CustomAnnotation;
use Phan\PluginV3;
use Phan\PluginV3\CustomAnnotationCapability;

/**
 * A plugin that declares an annotation with the prefix `phan-x-` but not in lowercase: Phan fails to load the plugins (see test.sh).
 */
final class InvalidNameTestPlugin extends PluginV3 implements CustomAnnotationCapability
{
    public function getCustomAnnotations(): array
    {
        return ['phan-x-Invalid-Name' => 'Not in lowercase'];
    }

    public function handleCustomAnnotation(CodeBase $code_base, AddressableElementInterface $element, CustomAnnotation $annotation): void
    {
    }
}

return new InvalidNameTestPlugin();
