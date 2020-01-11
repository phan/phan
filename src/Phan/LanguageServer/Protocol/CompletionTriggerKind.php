<?php

declare(strict_types=1);

namespace Phan\LanguageServer\Protocol;

/**
 * How a completion was triggered
 * @suppress PhanUnreferencedPublicClassConstant these are listed for completeness
 */
class CompletionTriggerKind
{
    /**
     * Completion was triggered by invoking it manually or by using the API.
     */
    public const INVOKED = 1;

    /**
     * Completion was triggered by a trigger character.
     */
    public const TRIGGER_CHARACTER = 2;

    /**
     * Completion was re-triggered as the current completion list is incomplete.
     */
    public const TRIGGER_FOR_INVALID_COMPLETIONS = 3;
}
