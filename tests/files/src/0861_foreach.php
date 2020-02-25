<?php
/**
 * @template T
 * @method T current()
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
