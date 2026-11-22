<?php

declare(strict_types=1);

use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Element\AddressableElement;
use Phan\Language\Element\AddressableElementInterface;
use Phan\Language\Element\Comment\CustomAnnotation;
use Phan\Language\ElementContext;
use Phan\PluginV3;
use Phan\PluginV3\CustomAnnotationCapability;

/**
 * The second test plugin of the custom annotation fixture suite (see test.sh), loaded after CustomAnnotationTestPlugin.
 *
 * It declares `(at)phan-test-shared`, which CustomAnnotationTestPlugin declares too (both plugins are called),
 * and `(at)phan-test-second`, and emits PhanPluginCustomAnnotationSecond for each occurrence.
 */
final class CustomAnnotationSecondTestPlugin extends PluginV3 implements CustomAnnotationCapability
{
    public function getCustomAnnotations(): array
    {
        return [
            'phan-test-shared' => 'Also declared by the first test plugin',
            'phan-test-second' => 'Declared by the second test plugin',
        ];
    }

    public function handleCustomAnnotation(CodeBase $code_base, AddressableElementInterface $element, CustomAnnotation $annotation): void
    {
        if (!$element instanceof AddressableElement) {
            throw new AssertionError('Expected an AddressableElement');
        }
        $this->emitIssue(
            $code_base,
            (new ElementContext($element))->withLineNumberStart($annotation->getLineno()),
            'PhanPluginCustomAnnotationSecond',
            'The second plugin saw @{STRING_LITERAL} on {CODE} with the text {STRING_LITERAL}',
            [$annotation->getName(), (string)$element->getFQSEN(), json_encode($annotation->getText())],
            Issue::SEVERITY_LOW
        );
    }
}

return new CustomAnnotationSecondTestPlugin();
