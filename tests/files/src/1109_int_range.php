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
takesRange(101);

/**
 * @param int-range<-10, -1> $value
 */
function takesNegativeRange($value): void {}

takesNegativeRange(-5);
takesNegativeRange(0);

/**
 * @param int-range<10, 5> $value
 */
function takesReversedRange($value): void {}

takesReversedRange(8);
takesReversedRange(4);

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
