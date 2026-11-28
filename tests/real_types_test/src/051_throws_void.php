<?php

// A call of a function with (at)throws void throws nothing.
namespace NS051;
class Quiet {
    /** @throws void */
    public function run(): void {}
    /** @throws void */
    public static function srun(): void {}
}
/** @throws void */
function quiet(): void {}
/** @throws \RuntimeException */
function loud(): void { throw new \RuntimeException('x'); }
function caller(Quiet $q): void {
    quiet();
    $q->run();
    Quiet::srun();
    loud();
}
