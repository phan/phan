<?php

/**
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnFunction
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnClass
 * @phan-file-suppress PhanPluginUseReturnValueNoopVoid
 * @phan-file-suppress PhanTemplateTypeNotUsedInFunctionReturn
 * @phan-file-suppress PhanUnusedGlobalFunctionParameter
 */

/**
 * @template U
 */
class Collection
{
    /**
     * @var array<U>
     */
    private array $items;

    /**
     * @param array<U> $items
     */
    public function __construct(array $items)
    {
        $this->items = $items;
    }
}

/**
 * @template T of Collection<string>
 * @param T $collection
 */
function processStringCollection($collection): void {}

processStringCollection(new Collection(['a', 'b']));
processStringCollection(new Collection([123]));
