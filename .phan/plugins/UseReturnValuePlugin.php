<?php

declare(strict_types=1);

// Moved to src/Phan/Plugin/Internal/UseReturnValuePlugin.php for autoloading convenience.
// This may become a core part of Phan.
use Phan\Plugin\Internal\UseReturnValuePlugin;

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new UseReturnValuePlugin();
