<?php

// @phan-file-suppress PhanUnusedGlobalFunctionParameter,PhanUnreferencedFunction

namespace NS15;

/**
 * This description is not redundant!
 *
 * @param string $x
 */
function descriptionAndSingleRedundantParameter( string $x ): void {}

/**
 * This description is not redundant!
 *
 * @param string $x
 * @param int $y
 * @param bool $z
 */
function descriptionAndMultipleRedundantParameter( string $x, int $y, bool $z ): void {}

/**
 * This description is not redundant!
 *
 * @param string $x
 * @param string[] $y
 * @param bool $z
 */
function descriptionAndAllButOneParametersRedundant( string $x, array $y, bool $z ): void {}

/**
 * @param string $x
 * @return string[]
 */
function singleRedundantParameterAndNonRedundantReturn( string $x ): array {
    return ['a'];
}

/**
 * @param string $x
 * @param int $y
 * @return string[]
 */
function multipleRedundantParametersAndNonRedundantReturn( string $x, int $y ): array {
    return ['a'];
}

/**
 * Useful description goes here.
 * @param string $x
 * @param int $y
 * @return string[]
 */
function descriptionRedundantParametersAndNonRedundantReturn( string $x, int $y ): array {
    return ['a'];
}

/**
 * Description
 * @param string $x
 * Text that might describe the parameter
 */
function textAfterParam( string $x ): void {}

/**
 * @note This is not about the param
 * @param string $x
 */
function annotationBeforeParam( string $x ): void {}

/**
 * @param string $x
 * @custom-annotation That might describe the parameter
 */
function annotationAfterParam( string $x ): void {}

/**
 * @param string $x
 * @return string[]
 * @custom-annotation We assume that this isn't about any parameter
 */
function annotationAfterParamAndReturn( string $x ): array {
    return ['a'];
}

/**
 * Useful description.
 * @param string $a
 * @phan-param int $b
 */
function usingPhanParam(string $a, int $b): void {}

/**
 * @param string $a
 * @return string[]
 * @param int $b
 */
function paramAfterReturn(string $a, int $b): array {
    return ['a'];
}

/**
 * Description
 *
 * @param string $a
 */
function emptyLineBeforeParamWithOnlyBefore(string $a): void {}

/**
 * Description
 * @param string $a
 *
 */
function emptyLineAfterParamWithOnlyBefore(string $a): void {}

/**
 *
 * @param string $a
 * @return string[]
 */
function emptyLineBeforeParamWithOnlyAfter(string $a): array {
    return ['a'];
}

/**
 * @param string $a
 *
 * @return string[]
 */
function emptyLineAfterParamWithOnlyAfter(string $a): array {
    return ['a'];
}

/**
 * Description
 *
 * @param string $a
 * @return string[]
 */
function emptyLineBeforeParamWithBeforeAndAfter(string $a): array {
    return ['a'];
}

/**
 * Description
 * @param string $a
 *
 * @return string[]
 */
function emptyLineAfterParamWithBeforeAndAfter(string $a): array {
    return ['a'];
}

/**
 * Description
 *
 * @param string $a
 *
 * @return string[]
 */
function emptyLinesAroundParamWithBeforeAndAfter(string $a): array {
    return ['a'];
}

/**
 * Description
 *
 * @param string $a
 *
 */
function emptyLinesAroundParamWithOnlyBefore(string $a): void {}

/**
 *
 * @param string $a
 *
 * @return string[]
 */
function emptyLinesAroundParamWithOnlyAfter(string $a): array {
    return ['a'];
}
