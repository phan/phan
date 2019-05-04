<?php declare(strict_types=1);

namespace Phan\PluginV3;

use Exception;

/**
 * This is thrown to prevent Phan from running its own analysis after analyzeFunctionCall.
 *
 * This is used for tricky signatures such as join, which can take (array, string) or (string, array) when there are two arguments.
 */
class StopParamAnalysisException extends Exception
{
}
