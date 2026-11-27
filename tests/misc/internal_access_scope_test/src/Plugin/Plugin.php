<?php
namespace App\Plugin;

// `App\Plugin\` is the prefix of an installed package, and is longer than the prefix `App\` of the project:
// this is another package than the project, in both directions.
class Plugin {
    /** @internal */
    public static function innerOfPlugin(): int {
        return \App\Core::inner();
    }
}
