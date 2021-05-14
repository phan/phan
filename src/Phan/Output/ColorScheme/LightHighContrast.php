<?php

declare(strict_types=1);

namespace Phan\Output\ColorScheme;

/**
 * Contains colors suitable for output on a white background.
 * @suppress PhanUnreferencedClass this is used dynamically
 */
class LightHighContrast
{
    /** @suppress PhanUnreferencedPublicClassConstant this is used dynamically */
    public const DEFAULT_COLOR_FOR_TEMPLATE = [
        'CLASS'         => 'blue',
        'CLASSLIKE'     => 'blue',
        'CODE'          => 'magenta',
        'COMMENT'       => 'dark_gray',
        'CONST'         => 'blue',
        'COUNT'         => 'blue',
        'DETAILS'       => 'dark_gray',
        'ENUM'          => 'blue',
        'FILE'          => 'light_magenta',
        'FUNCTIONLIKE'  => 'green',
        'FUNCTION'      => 'green',
        'INDEX'         => 'blue',
        'INTERFACE'     => 'blue',
        'ISSUETYPE'     => 'blue',  // used by Phan\Output\Printer, for minor issues
        'ISSUETYPE_CRITICAL' => 'red',  // for critical issues, e.g. "PhanUndeclaredMethod"
        'ISSUETYPE_NORMAL' => 'bg_yellow',  // for normal issues
        'LINE'          => 'dark_gray',
        'METHOD'        => 'green',
        'NAMESPACE'     => 'dark_gray',
        'OPERATOR'      => 'dark_gray',
        'PARAMETER'     => 'magenta',
        'PROPERTY'      => 'blue',
        'SCALAR'        => 'green',
        'STRING_LITERAL' => 'green',
        'SUGGESTION'    => 'dark_gray',
        'TYPE'          => 'blue',
        'TRAIT'         => 'blue',
        'VARIABLE'      => 'bg_yellow',
    ];
}
