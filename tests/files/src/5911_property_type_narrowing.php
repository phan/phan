<?php

// Bug 5440: When assigning a wider union type to a typed property,
// Phan should narrow the local scope type to match the declared property type.

class Config5911 {
    public Reader5911|Writer5911|null $handler = null;
    public static function create(): self {
        $c = new self();
        $c->handler = new CsvReader5911();
        return $c;
    }
}

abstract class Reader5911 {
    abstract public function readLines(): string;
}

class CsvReader5911 extends Reader5911 {
    public function readLines(): string {
        return '';
    }
}

abstract class Writer5911 {
    abstract public function writeLines(string $data): void;
}

// Test 1: static property with wider union type assignment
abstract class StaticProcessor5911 {
    private static Reader5911 $reader;

    static function process(): string {
        $config = Config5911::create();
        self::$reader = $config->handler;
        // readLines() exists on Reader5911 but not Writer5911
        // Since self::$reader is declared as Reader5911, this should be fine
        return self::$reader->readLines();
    }
}

// Test 2: instance property with wider union type assignment
class InstanceProcessor5911 {
    private Reader5911 $reader;
    public function __construct() { $this->reader = new CsvReader5911(); }

    function process(): string {
        $config = Config5911::create();
        $this->reader = $config->handler;
        // Same as Test 1 but for instance property
        return $this->reader->readLines();
    }
}

// Test 3: Should still warn for genuinely wrong method calls
class WrongMethodProcessor5911 {
    private Writer5911 $writer;
    public function __construct(Writer5911 $w) { $this->writer = $w; }

    function process(): string {
        $config = Config5911::create();
        $this->writer = $config->handler;
        // After narrowing, $this->writer is typed as Writer5911 (the declared type)
        return $this->writer->readLines();
    }
}
