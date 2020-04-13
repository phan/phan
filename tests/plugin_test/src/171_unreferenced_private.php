<?php

namespace NS171;

class Subclass extends Base {
    private static function testStatic() {
        echo "In subclass\n";
    }
    private function testInstance() {
        echo "In subclass\n";
    }
}

class Base {
    private static function testStatic() {
        echo "In base testStatic\n";
    }
    private function testInstance() {
        echo "In base testInstance\n";
    }

    public function main() {
        $this->testStatic();
        $this->testInstance();
    }
}
(new Subclass())->main();
