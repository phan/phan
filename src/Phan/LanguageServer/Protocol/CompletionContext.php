<?php
declare(strict_types=1);

namespace Phan\LanguageServer\Protocol;

/**
 * Contains additional information about the context in which a completion request is triggered.
 * @phan-file-suppress PhanWriteOnlyPublicProperty this is sent to the language client
 */
class CompletionContext
{
    /**
     * How the completion was triggered.
     *
     * @var int|null
     */
    public $triggerKind;

    /**
     * The trigger character (a single character) that has trigger code complete.
     * Is null if `triggerKind !== CompletionTriggerKind::TRIGGER_CHARACTER`
     *
     * @var string|null
     */
    public $triggerCharacter;

    public function __construct(int $triggerKind = null, string $triggerCharacter = null)
    {
        $this->triggerKind = $triggerKind;
        $this->triggerCharacter = $triggerCharacter;
    }
}
