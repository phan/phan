<?php

declare(strict_types=1);

namespace Phan\LanguageServer;

/**
 * Generates unique, incremental IDs for use as request IDs
 *
 * Source: https://github.com/felixfbecker/php-language-server/tree/master/src/IdGenerator.php
 * See ../../../../LICENSE.LANGUAGE_SERVER
 */
class IdGenerator
{
    /**
     * @var int an incrementing counter for generating unique request IDs
     */
    public $counter = 1;

    /**
     * Returns a unique ID
     */
    public function generate(): int
    {
        return $this->counter++;
    }
}
