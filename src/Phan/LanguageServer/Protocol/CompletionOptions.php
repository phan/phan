<?php

declare(strict_types=1);

namespace Phan\LanguageServer\Protocol;

/**
 * Completion options.
 *
 * @phan-file-suppress PhanWriteOnlyPublicProperty these are sent to the language client
 */
class CompletionOptions
{
    /**
     * The server provides support to resolve additional information for a completion
     * item.
     *
     * @var bool|null
     */
    public $resolveProvider;

    /**
     * The characters that trigger completion automatically.
     *
     * @var string[]|null
     */
    public $triggerCharacters;
}
