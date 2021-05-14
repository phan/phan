<?php

declare(strict_types=1);

namespace Phan\Output\ColorScheme;

/**
 * Contains colors similar to vim's default color scheme
 * (with higher contrast against a black background and preferring related colors over plain text)
 * @suppress PhanUnreferencedClass this is used dynamically
 */
class Vim
{
    /** @suppress PhanUnreferencedPublicClassConstant this is used dynamically */
    public const DEFAULT_COLOR_FOR_TEMPLATE = [
        'CLASS'         => 'green',
        'CLASSLIKE'     => 'green',
        'CODE'          => 'magenta',
        'COMMENT'       => 'light_blue',
        'CONST'         => 'magenta',
        'COUNT'         => 'red',
        'DETAILS'       => 'none',
        'ENUM'          => 'green',
        'FILE'          => 'light_blue',
        'FUNCTIONLIKE'  => 'magenta',
        'FUNCTION'      => 'magenta',
        'INDEX'         => 'yellow',
        'INTERFACE'     => 'green',
        'ISSUETYPE'     => 'light_yellow',  // used by Phan\Output\Printer, for minor issues
        'ISSUETYPE_CRITICAL' => 'bg_red',  // for critical issues, e.g. "PhanUndeclaredMethod"
        'ISSUETYPE_NORMAL' => 'black,bg_light_yellow',  // for normal issues
        'LINE'          => 'yellow',
        'METHOD'        => 'magenta',
        'NAMESPACE'     => 'green',
        'OPERATOR'      => 'yellow',
        'PARAMETER'     => 'light_cyan',
        'PROPERTY'      => 'light_blue',
        'SCALAR'        => 'red',
        'STRING_LITERAL' => 'red',
        'SUGGESTION'    => 'light_gray',
        'TYPE'          => 'light_blue',
        'TRAIT'         => 'green',
        'VARIABLE'      => 'light_cyan',
    ];
}
