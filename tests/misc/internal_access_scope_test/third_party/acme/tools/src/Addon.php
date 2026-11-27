<?php
namespace Acme\Lib\Extra;

// A package without `install-path` in installed.json (Composer 1): its directory is `third_party/acme/tools`.
// Its namespace is within the namespace of acme/lib, which does not matter.
class Addon {
    /** @internal */
    public static function inner(): int {
        return 3;
    }
}
