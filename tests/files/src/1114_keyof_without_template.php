<?php

/**
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnFunction
 * @phan-file-suppress PhanPluginCanUseUnionParamType
 * @phan-file-suppress PhanPluginUseReturnValueNoopVoid
 * @phan-file-suppress PhanUnusedGlobalFunctionParameter
 */

/**
 * @param key-of $key
 */
function acceptsKey($key): void {}

acceptsKey('foo');
acceptsKey(123);
