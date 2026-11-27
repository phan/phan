<?php
namespace App\Module;

// A package that is installed outside of the vendor directory (`install-path` of installed.json),
// in a directory that is analyzed: it is that package, and not the project.
class Module {
    /** @internal */
    public static function inner(): int {
        return \App\Core::inner();
    }
}
