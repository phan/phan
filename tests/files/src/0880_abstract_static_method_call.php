<?php
namespace AbstractStaticTest;

class Gift {
}

interface GiftBackend {
    public static function create(): Gift;
}

class GiftProvider {
    public $backend;
    public function __construct(GiftBackend $b) {
        $this->backend = $b;
    }

    public function create(): Gift {
        return $this->backend::create(); // should not warn
    }

    public function createInvalid(): Gift {
        $backendClass = GiftBackend::class;
        return $backendClass::create();  // this is an abstract method
    }

    public function createInvalid2(): Gift {
        $backendClass = GiftBackend::class;
        return $backendClass->create();  // this needs an object, not a string
    }
}
