<?php declare(strict_types=1);
namespace Phan\Language\Internal;

/**
 * A mapping from class name to the type of dynamic
 * properties on that class, if dynamic properties
 * are allowed.
 *
 * These values have been manually entered.
 */
return [
    'stdclass' => 'mixed',
    'simplexmlelement' => 'simplexmlelement',
];
