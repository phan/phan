<?php
namespace App\Sub;

use Acme\Lib\Extra\Addon;
use Acme\Lib\Internals\Helper;
use Acme\Lib\Tool;
use App\Core;
use App\Hidden;

// A file of the project: not reported, in any namespace.
function ownPackage(Core $core): int {
    $hidden = new Hidden();
    return Core::inner() + $core->state + Core::LIMIT + (int)($hidden instanceof Hidden);
}

// Files of installed packages: reported.
function otherPackages(): int {
    return Tool::secret() + Tool::SECRET + Helper::help() + Addon::inner();
}

// A file of a directory that is not analyzed: reported.
function bundled(): int {
    return \App\Bundled\Bundled::inner();
}

// An internal method of another package that a class of the project inherits: reported.
function inherited(\App\Child $child): int {
    return $child->innerOfBase();
}

// A method that is internal because the class of the project is: not reported, although the method is declared in another package.
function internalClass(\App\InternalChild $child): int {
    return $child->open();
}

// A package that is installed outside of the vendor directory: reported.
function module(): int {
    return \App\Module\Module::inner();
}
