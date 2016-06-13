<?php
declare(strict_types = 1);

$attribute = new DOMAttr('name');
$attribute = new DOMAttr('name', 'value');
echo $attribute->name;
$attributeOwnerElement = $attribute->ownerElement;
echo $attribute->schemaTypeInfo;
echo $attribute->specified;
$attribute->value = 'value';

$document = new DOMDocument();
echo $document->actualEncoding;
$documentConfig = $document->config;
$documentDoctype = $document->doctype;
$documentDocumentElement = $document->documentElement;
$document->documentURI = 'documentURI';
$document->encoding = 'encoding';
$document->formatOutput = true;
$documentImplementation = $document->implementation;
$document->preserveWhiteSpace = true;
$document->recover = true;
$document->resolveExternals = true;
$document->standalone = true;
$document->strictErrorChecking = true;
$document->substituteEntities = true;
$document->validateOnParse = true;
$document->version = 'version';
echo $document->xmlEncoding;
$document->xmlStandalone = true;
$document->xmlVersion = 'xmlVersion';

$documentType = new DOMDocumentType();
echo $documentType->publicId;
echo $documentType->systemId;
echo $documentType->name;
$documentTypeEntities = $documentType->entities;
$documentTypeNotations = $documentType->notations;
echo $documentType->internalSubset;

$element = new DOMElement('name');
echo $element->schemaTypeInfo;
echo $element->tagName;

$entity = new DOMEntity();
echo $entity->publicId;
echo $entity->systemId;
echo $entity->notationName;
$entity->actualEncoding = 'actualEncoding';
echo $entity->encoding;
echo $entity->version;

$node = new DOMNode();
echo $node->nodeName;
$node->nodeValue = 'nodeValue';
echo $node->nodeType;
$nodeParentNode = $node->parentNode;
$nodeChildNodes = $node->childNodes;
$nodeFirstChild = $node->firstChild;
$nodeLastChild = $node->lastChild;
$nodePreviousSibling = $node->previousSibling;
$nodeNextSibling= $node->nextSibling;
$nodeAttributes = $node->attributes;
$nodeOwnerDocument = $node->ownerDocument;
echo $node->namespaceURI;
$node->prefix = 'prefix';
echo $node->localName;
echo $node->baseURI;
$node->textContent = 'textContent';

$notation = new DOMNotation();
echo $notation->publicId;
echo $notation->systemId;

$text = new DOMText('value');
echo $text->wholeText;
