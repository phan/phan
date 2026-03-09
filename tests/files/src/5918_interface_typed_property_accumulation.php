<?php

// Verify that concrete types are accumulated onto interface-typed properties.
// When a property is declared as an interface type and assigned a concrete implementation,
// phan should accumulate the concrete type (e.g. OutputInterface|ConsoleOutput).

interface OutputInterface5918 {
    public function write(string $msg): void;
}

class ConsoleOutput5918 implements OutputInterface5918 {
    public function write(string $msg): void {}
    public function writeln(string $msg): void {}
}

class App5918 {
    private OutputInterface5918 $output;

    public function __construct() {
        $this->output = new ConsoleOutput5918();
    }

    public function run(): void {
        $o = $this->output;
        '@phan-debug-var $o';
    }
}
