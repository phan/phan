<?php

/**
 * Regression fixture ensuring float array offsets no longer crash analysis (issue #4749).
 *
 * @phan-file-suppress PhanPluginNoCommentOnFunction
 * @phan-file-suppress PhanPluginUnknownArrayFunctionParamType
 * @phan-file-suppress PhanPluginUnknownArrayFunctionReturnType
 */
function assignToFloatKey(array $list, int $value): array
{
    $list[1.2] = $value;
    return $list;
}
