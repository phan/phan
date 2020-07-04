<?php

declare(strict_types=1);

namespace Phan\PluginV3;

use Exception;

/**
 * This is thrown to prevent Phan from enabling a configured plugin, e.g. when its dependencies are missing.
 */
class UnloadablePluginException extends Exception
{
}
