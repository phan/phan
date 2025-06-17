<?php

// @phan-file-suppress PhanUnusedGlobalFunctionParameter,PhanUnreferencedFunction

namespace NS15;

/**
 * This description is not redundant!
 */
function descriptionAndSingleRedundantParameter( string $x ): void {}

/**
 * This description is not redundant!
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
 * @return string[]
 */
function singleRedundantParameterAndNonRedundantReturn( string $x ): array {
    return ['a'];
}

/**
 * @return string[]
 */
function multipleRedundantParametersAndNonRedundantReturn( string $x, int $y ): array {
    return ['a'];
}

/**
 * Useful description goes here.
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
 */
function annotationBeforeParam( string $x ): void {}

/**
 * @param string $x
 * @custom-annotation That might describe the parameter
 */
function annotationAfterParam( string $x ): void {}

/**
 * @return string[]
 * @custom-annotation We assume that this isn't about any parameter
 */
function annotationAfterParamAndReturn( string $x ): array {
    return ['a'];
}

/**
 * Useful description.
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
 */
function emptyLineBeforeParamWithOnlyBefore(string $a): void {}

/**
 * Description
 */
function emptyLineAfterParamWithOnlyBefore(string $a): void {}

/**
 * @return string[]
 */
function emptyLineBeforeParamWithOnlyAfter(string $a): array {
    return ['a'];
}

/**
 * @return string[]
 */
function emptyLineAfterParamWithOnlyAfter(string $a): array {
    return ['a'];
}

/**
 * Description
 *
 * @return string[]
 */
function emptyLineBeforeParamWithBeforeAndAfter(string $a): array {
    return ['a'];
}

/**
 * Description
 *
 * @return string[]
 */
function emptyLineAfterParamWithBeforeAndAfter(string $a): array {
    return ['a'];
}

/**
 * Description
 *
 * @return string[]
 */
function emptyLinesAroundParamWithBeforeAndAfter(string $a): array {
    return ['a'];
}

/**
 * Description
 */
function emptyLinesAroundParamWithOnlyBefore(string $a): void {}

/**
 * @return string[]
 */
function emptyLinesAroundParamWithOnlyAfter(string $a): array {
    return ['a'];
}
