<?php

declare(strict_types=1);

namespace Phan\LanguageServer\Protocol;

/**
 * Source: https://github.com/felixfbecker/php-language-server/tree/master/src/Protocol/DiagnosticSeverity.php
 * See ../../../../LICENSE.LANGUAGE_SERVER
 */
abstract class DiagnosticSeverity
{
    /**
     * Reports an error.
     */
    public const ERROR = 1;

    /**
     * Reports a warning.
     */
    public const WARNING = 2;

    /**
     * Reports an information.
     */
    public const INFORMATION = 3;

    /**
     * Reports a hint.
     * @suppress PhanUnreferencedPublicClassConstant unused, but documented
     */
    public const HINT = 4;
}
