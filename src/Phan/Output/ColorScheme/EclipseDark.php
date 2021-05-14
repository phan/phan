<?php

declare(strict_types=1);

namespace Phan\Output\ColorScheme;

/**
 * Contains colors similar to eclipse's dark theme
 * @suppress PhanUnreferencedClass this is used dynamically
 */
class EclipseDark
{
    /** @suppress PhanUnreferencedPublicClassConstant this is used dynamically */
    public const DEFAULT_COLOR_FOR_TEMPLATE = [
        'CLASS'         => 'light_blue',
        'CLASSLIKE'     => 'light_blue',
        'CODE'          => 'magenta',
        'COMMENT'       => 'light_gray',
        'CONST'         => 'light_blue',
        'COUNT'         => 'light_blue',
        'DETAILS'       => 'light_gray',
        'ENUM'          => 'light_blue',
        'FILE'          => 'light_cyan',
        'FUNCTIONLIKE'  => 'light_green',
        'FUNCTION'      => 'light_green',
        'INDEX'         => 'light_blue',
        'INTERFACE'     => 'light_blue',
        'ISSUETYPE'     => 'light_blue',  // used by Phan\Output\Printer, for minor issues
        'ISSUETYPE_CRITICAL' => 'red',  // for critical issues, e.g. "PhanUndeclaredMethod"
        'ISSUETYPE_NORMAL' => 'light_yellow',  // for normal issues
        'LINE'          => 'light_gray',
        'METHOD'        => 'green',
        'NAMESPACE'     => 'light_gray',
        'OPERATOR'      => 'light_gray',
        'PARAMETER'     => 'magenta',
        'PROPERTY'      => 'light_blue',
        'SCALAR'        => 'green',
        'STRING_LITERAL' => 'green',
        'SUGGESTION'    => 'light_gray',
        'TYPE'          => 'light_blue',
        'TRAIT'         => 'light_blue',
        'VARIABLE'      => 'yellow',
    ];
}
