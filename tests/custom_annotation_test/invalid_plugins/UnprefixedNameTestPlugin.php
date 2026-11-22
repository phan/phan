<?php

declare(strict_types=1);

use Phan\CodeBase;
use Phan\Language\Element\AddressableElementInterface;
use Phan\Language\Element\Comment\CustomAnnotation;
use Phan\PluginV3;
use Phan\PluginV3\CustomAnnotationCapability;

/**
 * A plugin that declares an annotation without the prefix `phan-x-`: Phan fails to load the plugins (see test.sh).
 */
final class UnprefixedNameTestPlugin extends PluginV3 implements CustomAnnotationCapability
{
    public function getCustomAnnotations(): array
    {
        return ['phan-x-test-mark' => '', 'phan-my-assert' => 'Without the prefix'];
    }

    public function handleCustomAnnotation(CodeBase $code_base, AddressableElementInterface $element, CustomAnnotation $annotation): void
    {
    }
}

return new UnprefixedNameTestPlugin();
