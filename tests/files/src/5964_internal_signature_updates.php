<?php
// Signatures of internal functions and methods that were out of date in Phan's signature map.
// None of these calls should emit an issue except where noted.

function signatures_5964(string $file, array $a, ArrayObject $ao, DOMElement $element, DOMDocument $doc, Phar $phar) {
    echo hash_file('sha256', $file, false, []);       // $options (PHP 8.1)
    $ctx = hash_init('sha256', 0, '', []);            // $options (PHP 8.1)
    echo hash_final($ctx);
    var_export(array_replace($a));                    // ...$replacements is optional
    var_export(array_replace_recursive($a));
    debug_zval_dump($a, $a);                          // ($value, ...$values)
    var_export($ao->asort(SORT_STRING));              // $flags
    var_export($ao->ksort(SORT_STRING));
    $element->append('text', $doc->createElement('b')); // ...$nodes
    $element->before('text');
    $xpath = new DOMXPath($doc, false);               // $registerNodeNS
    var_export($xpath);
    var_export(mb_str_split('abc', length: 2));       // $length (was named $split_length)
    var_export($phar->copy(from: 'a', to: 'b'));      // ($from, $to) (the names were swapped)
    echo $phar['file']->isCompressed() ? 'yes' : 'no'; // offsetGet() returns PharFileInfo
    echo hash_file('sha256', $file, false, [], 'extra'); // still reported: too many arguments
}

function fiber_5964() {
    $fiber = new Fiber(function (int $x): int {
        return Fiber::suspend($x);                    // $value
    });
    var_export($fiber->start(1));                     // ...$args
    var_export($fiber->resume(2));                    // $value
}

// tokenize() returns the subclass it is called on
class Token5964 extends PhpToken {
    public function lower(): string {
        return strtolower($this->text);
    }
}
foreach (Token5964::tokenize('<?php 1;') as $token) {
    echo $token->lower();
}

// XMLReader::open() returns a new XMLReader when called statically
$reader = XMLReader::open('file.xml');
if ($reader) {
    while ($reader->read()) {
        echo $reader->name;
    }
}
