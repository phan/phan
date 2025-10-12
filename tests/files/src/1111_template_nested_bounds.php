<?php

/**
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnFunction
 * @phan-file-suppress PhanPluginUseReturnValueNoopVoid
 * @phan-file-suppress PhanTemplateTypeNotUsedInFunctionReturn
 * @phan-file-suppress PhanUnusedGlobalFunctionParameter
 */

/**
 * @template T of \Iterator
 * @param array<T> $items
 */
function consumeIterableArray(array $items): void {}

consumeIterableArray([new ArrayIterator([])]);
consumeIterableArray([new stdClass()]);

/**
 * @template T of callable
 * @param array{callback:T} $config
 */
function consumeConfig(array $config): void {}

consumeConfig(['callback' => static function (): void {}]);
consumeConfig(['callback' => new stdClass()]);
