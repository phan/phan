<?php

/**
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnFunction
 * @phan-file-suppress PhanPluginCanUseParamType
 * @phan-file-suppress PhanPluginUseReturnValueNoopVoid
 * @phan-file-suppress PhanUnusedGlobalFunctionParameter
 * @phan-file-suppress PhanPluginCanUseReturnType
 */

/**
 * @param int-range<1, 10> $value
 */
function takesRange($value): void {}

takesRange(5);
takesRange(0);
takesRange(12);

/**
 * @return int-range<1, 10>
 */
function returnsValid()
{
    return 7;
}

/**
 * @return int-range<1, 10>
 */
function returnsTooLow()
{
    return 0;
}

/**
 * @return int-range<1, 10>
 */
function returnsTooHigh()
{
    return 11;
}
