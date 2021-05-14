<?php

declare(strict_types=1);

namespace Phan\Output\ColorScheme;

/**
 * Contains colors similar to VS Code's default color scheme
 * (with higher contrast against a black background and preferring related colors over plain text)
 * @suppress PhanUnreferencedClass this is used dynamically
 */
class Code
{
    /** @suppress PhanUnreferencedPublicClassConstant this is used dynamically */
    public const DEFAULT_COLOR_FOR_TEMPLATE = [
        'CLASS'         => 'light_green',
        'CLASSLIKE'     => 'light_green',
        'CODE'          => 'magenta',
        'COMMENT'       => 'green',
        'CONST'         => 'none',
        'COUNT'         => 'light_yellow',
        'DETAILS'       => 'none',
        'ENUM'          => 'light_green',
        'FILE'          => 'light_gray',
        'FUNCTIONLIKE'  => 'light_yellow',
        'FUNCTION'      => 'light_yellow',
        'INDEX'         => 'light_gray',
        'INTERFACE'     => 'light_green',
        'ISSUETYPE'     => 'light_blue',  // used by Phan\Output\Printer, for minor issues
        'ISSUETYPE_CRITICAL' => 'light_red',  // for critical issues, e.g. "PhanUndeclaredMethod"
        'ISSUETYPE_NORMAL' => 'light_yellow',  // for normal issues
        'LINE'          => 'light_gray',
        'METHOD'        => 'light_yellow',
        'NAMESPACE'     => 'light_gray',
        'OPERATOR'      => 'light_gray',
        'PARAMETER'     => 'light_cyan',
        'PROPERTY'      => 'light_cyan',
        'SCALAR'        => 'light_green',
        'STRING_LITERAL' => 'red',
        'SUGGESTION'    => 'light_gray',
        'TYPE'          => 'light_blue',
        'TRAIT'         => 'light_green',
        'VARIABLE'      => 'light_cyan',
    ];
}
