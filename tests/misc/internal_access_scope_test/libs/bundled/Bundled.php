<?php
namespace App\Bundled;

// Code in a directory of `exclude_analysis_directory_list` that is not the vendor directory
// is code of somebody else: this is another package than the project, whatever its namespace.
class Bundled {
    /** @internal */
    public static function inner(): int {
        return 6;
    }
}
