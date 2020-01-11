<?php

declare(strict_types=1);

namespace Phan\LanguageServer\Protocol;

/**
 * The file event type. Enum
 *
 * Source: https://github.com/felixfbecker/php-language-server/tree/master/src/Protocol/FileChangeType.php
 * See ../../../../LICENSE.LANGUAGE_SERVER
 */
abstract class FileChangeType
{
    /**
     * The file got created.
     * @suppress PhanUnreferencedPublicClassConstant
     */
    public const CREATED = 1;

    /**
     * The file got changed.
     */
    public const CHANGED = 2;

    /**
     * The file got deleted.
     */
    public const DELETED = 3;
}
