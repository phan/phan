<?php

interface OutputInterface {
    public function write(string $msg): void;
}

class ConsoleOutput implements OutputInterface {
    public function write(string $msg): void {
        echo $msg;
    }
    public function writeln(string $msg): void {
        echo $msg . "\n";
    }
}

/* @phan-suppress-next-line PhanUnreferencedClass */
class App {
    private OutputInterface $output;

    public function __construct() {
        $this->output = new ConsoleOutput();
    }

    /* @phan-suppress-next-line PhanUnreferencedPublicMethod */
    public function run(): void {
        // With the revert of PR #5300, phan accumulates ConsoleOutput onto
        // the interface-typed property, so it sees OutputInterface|ConsoleOutput.
        // This means writeln() is found (on ConsoleOutput), not undeclared.
        $this->output->writeln('hello');

        // write() exists on the interface — always works.
        $this->output->write('world');
    }
}
