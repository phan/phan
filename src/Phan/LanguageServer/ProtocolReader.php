<?php

declare(strict_types=1);

namespace Phan\LanguageServer;

use Sabre\Event\EmitterInterface;

/**
 * Must emit a "message" event with a Protocol\Message object as parameter
 * when a message comes in
 *
 * Must emit a "close" event when the stream closes
 *
 * Source: https://github.com/felixfbecker/php-language-server/tree/master/src/ProtocolReader.php
 * See ../../../LICENSE.LANGUAGE_SERVER
 * @phan-file-suppress PhanPluginNoCommentOnPublicMethod TODO: Add comments
 */
interface ProtocolReader extends EmitterInterface
{
    public function stopAcceptingNewRequests(): void;
}
