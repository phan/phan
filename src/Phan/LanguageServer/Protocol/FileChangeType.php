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
    const CREATED = 1;

    /**
     * The file got changed.
     */
    const CHANGED = 2;

    /**
     * The file got deleted.
     */
    const DELETED = 3;
}
