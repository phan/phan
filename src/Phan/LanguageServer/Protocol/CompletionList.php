<?php

declare(strict_types=1);

namespace Phan\LanguageServer\Protocol;

/**
 * Represents a collection of completion items to be presented in
 * the editor.
 * @phan-file-suppress PhanWriteOnlyPublicProperty these are sent to the language client
 * @phan-immutable
 */
class CompletionList
{
    /**
     * This list is not complete. Continuing to type should result in recomputing this
     * list.
     *
     * @var bool
     */
    public $isIncomplete;

    /**
     * The completion items.
     *
     * @var CompletionItem[]
     */
    public $items;

    /**
     * @param CompletionItem[] $items        The completion items.
     * @param bool             $isIncomplete This list is not complete. Continuing to type should result in recomputing this list.
     */
    public function __construct(array $items = [], bool $isIncomplete = false)
    {
        $this->items = $items;
        $this->isIncomplete = $isIncomplete;
    }
}
