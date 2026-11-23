<?php
// Deprecations of internal elements that php-src declares, and that Phan did not know

// Functions
function deprecated_functions013(): void {
    var_dump(assert_options(ASSERT_ACTIVE));              // 8.3, and the constant
    libxml_disable_entity_loader(true);                   // 8.0
}

// Global constants
var_dump(FILTER_SANITIZE_STRIPPED);                       // 8.1
var_dump(SUNFUNCS_RET_TIMESTAMP, DOM_PHP_ERR);            // 8.4
var_dump(ASSERT_BAIL, ASSERT_CALLBACK, ASSERT_WARNING);   // 8.3

// The driver constants of the PDO class
var_dump(PDO::SQLITE_ATTR_OPEN_FLAGS, PDO::SQLITE_DETERMINISTIC); // 8.5
var_dump(PDO::ATTR_ERRMODE);                              // not deprecated

// Methods
function deprecated_methods013(SplObjectStorage $storage, stdClass $o, ReflectionMethod $method, ReflectionParameter $parameter): void {
    $storage->attach($o);                                 // 8.5
    var_dump($storage->contains($o));                     // 8.5
    $storage->detach($o);                                 // 8.5
    $storage->offsetSet($o);                              // not deprecated
    $method->setAccessible(true);                         // 8.5
    var_dump($parameter->isArray());                      // 8.0
}

// A deprecated method that an internal subclass inherits
function inherited_methods013(SplTempFileObject $file, RecursiveArrayIterator $iterator): void {
    $file->setCsvControl(',', '"', '');                   // 8.6, from SplFileObject
    var_dump($file->getCsvControl());
    $iterator->ksort();                                   // 8.6, from ArrayIterator
    var_dump($file->eof());                               // not deprecated
}

// Properties
function deprecated_properties013(DOMDocument $document, DOMEntity $entity): void {
    var_dump($document->actualEncoding, $document->config);            // 8.4
    var_dump($entity->actualEncoding, $entity->encoding, $entity->version);
    var_dump($document->encoding, $entity->publicId);                  // not deprecated
}

// A class that declares its own property or method of the same name is not affected
class Document013 extends DOMDocument {
    /** @var string */
    public $mine = '';
}
class Storage013 {
    public function attach(object $o): void { var_dump($o); }
}
function user_elements013(Document013 $document, Storage013 $storage): void {
    var_dump($document->mine, $document->config);         // config is inherited: deprecated
    $storage->attach($document);
}
