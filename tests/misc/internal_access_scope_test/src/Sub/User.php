<?php
namespace App\Sub;

use Acme\Lib\Extra\Addon;
use Acme\Lib\Internals\Helper;
use Acme\Lib\Tool;
use App\Core;
use App\Hidden;

// The same package (the prefix `App\` of the composer.json of the project): not reported.
function ownPackage(Core $core): int {
    $hidden = new Hidden();
    return Core::inner() + $core->state + Core::LIMIT + (int)($hidden instanceof Hidden);
}

// Another package: reported.
function otherPackages(): int {
    return Tool::secret() + Tool::SECRET + Helper::help() + Addon::inner();
}
