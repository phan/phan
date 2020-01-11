<?php

declare(strict_types=1);

namespace Phan\Output\ColorScheme;

/**
 * Contains colors similar to VS Code's default color scheme
 * (with higher contrast against a black background and preferring related colors over plain text)
 * @suppress PhanUnreferencedClass this is used dynamically
 */
class Light
{
    /** @suppress PhanUnreferencedPublicClassConstant this is used dynamically */
    public const DEFAULT_COLOR_FOR_TEMPLATE = [
        'CLASS'         => 'green',
        'CLASSLIKE'     => 'green',
        'CODE'          => 'magenta',
        'COMMENT'       => 'green',
        'CONST'         => 'red',
        'COUNT'         => 'magenta',
        'DETAILS'       => 'green',
        'FILE'          => 'cyan',
        'FUNCTIONLIKE'  => 'yellow',
        'FUNCTION'      => 'yellow',
        'INDEX'         => 'magenta',
        'INTERFACE'     => 'green',
        'ISSUETYPE'     => 'light_blue',  // used by Phan\Output\Printer, for minor issues
        'ISSUETYPE_CRITICAL' => 'red',  // for critical issues, e.g. "PhanUndeclaredMethod"
        'ISSUETYPE_NORMAL' => 'light_red',  // for normal issues
        'LINE'          => 'dark_gray',
        'METHOD'        => 'yellow',
        'NAMESPACE'     => 'green',
        'OPERATOR'      => 'red',
        'PARAMETER'     => 'cyan',
        'PROPERTY'      => 'cyan',
        'SCALAR'        => 'magenta',
        'STRING_LITERAL' => 'magenta',
        'SUGGESTION'    => 'dark_gray',
        'TYPE'          => 'dark_gray',
        'TRAIT'         => 'green',
        'VARIABLE'      => 'cyan',
    ];
}
