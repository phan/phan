<?php

/**
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnFunction
 * @phan-file-suppress PhanPluginUseReturnValueNoopVoid
 * @phan-file-suppress PhanTemplateTypeNotUsedInFunctionReturn
 * @phan-file-suppress PhanUnusedGlobalFunctionParameter
 */

/**
 * @template T of Countable&Traversable
 * @param T $value
 */
function requireBoth($value): void {}
/** Simple Countable impl without Traversable */
class CountableOnly implements Countable
{
    public function count(): int
    {
        return 0;
    }
}

requireBoth(new ArrayObject());
requireBoth(new ArrayIterator());
requireBoth(new CountableOnly());
