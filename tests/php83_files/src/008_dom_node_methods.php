<?php
// Regression test: the PHP 8.3 signature delta listed the new DOMNode methods with their return type only,
// which made Phan believe they take no parameters (PhanParamTooManyInternal on every call).
function domNodes008(DOMDocument $doc, DOMElement $element, DOMText $text): bool {
    $doc->replaceChildren($element, 'text');
    $fragment = $doc->createDocumentFragment();
    $fragment->replaceChildren($text);
    $root = $element->getRootNode(['composed' => false]);
    $root2 = $text->getRootNode();
    // Too many arguments are still reported.
    $text->getRootNode([], 1);
    return $doc->contains($element) && $element->isEqualNode($text) && $root === $root2;
}
