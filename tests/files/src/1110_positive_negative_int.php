<?php

/**
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnFunction
 * @phan-file-suppress PhanPluginCanUseParamType
 * @phan-file-suppress PhanPluginCanUseReturnType
 * @phan-file-suppress PhanPluginUseReturnValueNoopVoid
 * @phan-file-suppress PhanUnusedGlobalFunctionParameter
*/

/**
 * @param positive-int $value
 */
function takesPositive($value): void {}

takesPositive(5);
takesPositive(0);

takesPositive(-3);

/**
 * @param negative-int $value
 */
function takesNegative($value): void {}

takesNegative(-2);
takesNegative(4);

/**
 * @return positive-int
 */
function returnsPositive()
{
    return 7;
}

/**
 * @return positive-int
 */
function returnsPositiveWrong()
{
    return -1;
}

/**
 * @return negative-int
 */
function returnsNegative()
{
    return -5;
}

/**
 * @return negative-int
 */
function returnsNegativeWrong()
{
    return 3;
}
