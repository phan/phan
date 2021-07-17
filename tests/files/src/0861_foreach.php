<?php
/**
 * @template T
 * @method T current() TODO: Support parent types of templates
 * @suppress PhanParamSignaturePHPDocMismatchReturnType TODO: Support parent types of templates
 */
class Set extends \SplObjectStorage
{
}
/**
 * @param Set<ArrayObject> $arrays
 * @return string
 */
function test(Set $arrays) {
    foreach ($arrays as $elem) {
        return $elem;
    }
    return 'default';
}
