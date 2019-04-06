<?php
namespace NS650;
/**
 * @var int
 * @phan-suppress PhanUnextractableAnnotation suppress should work.
 */
function example(): string {
    return 'str';
}
echo example();

/**
 * @var int
 * @phan-suppress PhanParamTooMany
 * (UnusedSuppressionPlugin should warn about PhanParamTooMany)
 */
function example2(): string {
    return 'str';
}
echo example2();
