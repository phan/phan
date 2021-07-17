<?php

declare(strict_types=1);

namespace Phan\Library;

use Closure;
use TypeError;

/**
 * A set of objects supporting union and
 * intersection
 *
 * @template T
 *
 * TODO: Start tracking that SplObjectStorage<T,T> extends ArrayAccess<T,T>
 *
 * - Afterwards, remove this boilerplate overriding methods of SplObjectStorage<T,T>
 *
 * @phan-file-suppress PhanParamSignaturePHPDocMismatchParamType TODO: Add a way to indicate in Phan that T is subtype of object
 * @method void attach(T $object,mixed $data = null)
 * @method void detach(T $object)
 * @method bool offsetExists(T $object)
 * @method bool offsetGet(T $object )
 * @method void offsetSet(T $object,mixed $data = null)
 * @method void offsetUnset(T $object)
 * @phan-suppress-next-line PhanParamSignaturePHPDocMismatchReturnType TODO: Add a way to indicate that T is subtype of object
 * @method T current()
 *
 * @phan-file-suppress PhanParamSignatureMismatchInternal for these comment method overrides
 * TODO: Make suppressions in the class doc comment work for magic methods.
 */
class Set extends \SplObjectStorage
{

    /**
     * @param iterable<T> $element_iterator
     * An optional set of items to add to the set
     */
    public function __construct($element_iterator = null)
    {
        foreach ($element_iterator ?? [] as $element) {
            $this->attach($element);
        }
    }

    /**
     * @return array<T>
     * An array of all elements in the set is returned
     */
    public function toArray(): array
    {
        return \iterator_to_array($this);
    }

    /**
     * @param Set<T> $other
     * A set of items to intersect with this set
     *
     * @return Set<T>
     * A new set which contains only items in this
     * Set and the given Set
     */
    public function intersect(Set $other): Set
    {
        $set = new Set();
        foreach ($this as $element) {
            if ($other->contains($element)) {
                $set->attach($element);
            }
        }
        return $set;
    }

    /**
     * @param Set<T>[] $set_list
     * A list of sets to intersect
     *
     * @return Set<T>
     * A new Set containing only the elements that appear in
     * all parameters
     * @suppress PhanUnreferencedPublicMethod potentially useful but currently unused
     */
    public static function intersectAll(array $set_list): Set
    {
        if (\count($set_list) === 0) {
            return new Set();
        }

        $intersected_set = \array_shift($set_list);
        if (!$intersected_set instanceof Set) {
            // impossible
            throw new TypeError('Saw non-Set in $set_list');
        }
        foreach ($set_list as $set) {
            $intersected_set = $intersected_set->intersect($set);
        }

        return $intersected_set;
    }

    /**
     * @param Set<T> $other
     * A set of items to union with this set
     *
     * @return Set<T>
     * A new set which contains only items in this
     * Set and the given Set.
     *
     * @suppress PhanUnreferencedPublicMethod
     */
    public function union(Set $other): Set
    {
        $set = new Set();
        $set->addAll($this);
        $set->addAll($other);
        return $set;
    }

    /**
     * @param Set<T>[] $set_list
     * A list of sets to intersect
     *
     * @return Set<T>
     * A new Set containing any element that appear in
     * any parameters
     * @suppress PhanUnreferencedPublicMethod potentially useful but currently unused
     */
    public static function unionAll(array $set_list): Set
    {
        if (\count($set_list) === 0) {
            return new Set();
        }

        $union_set = \array_shift($set_list);
        if (!$union_set instanceof Set) {
            // impossible
            throw new TypeError('Saw non-Set in $set_list');
        }
        foreach ($set_list as $set) {
            $union_set = $union_set->union($set);
        }

        return $union_set;
    }


    /**
     * @param T[] $element_list
     * @return bool
     * True if this set contains any elements in the given list
     * @suppress PhanUnreferencedPublicMethod potentially useful but currently unused
     */
    public function containsAny(array $element_list): bool
    {
        foreach ($element_list as $element) {
            if ($this->contains($element)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Closure(T):bool $closure
     * A closure taking a set element that returns a boolean
     * for which true will cause the element to be retained
     * and false will cause the element to be removed
     *
     * @return Set<T>
     * A new set for which all elements when passed to the given
     * closure return true
     * @suppress PhanUnreferencedPublicMethod potentially useful but currently unused
     */
    public function filter(Closure $closure): Set
    {
        $set = new Set();
        foreach ($this as $element) {
            if ($closure($element)) {
                $set->attach($element);
            }
        }
        return $set;
    }

    /**
     * @template TNew
     * @param Closure(T):TNew $closure
     * A closure that maps each element of this set
     * to a new element
     *
     * @return Set<TNew>
     * A new set containing the mapped values
     */
    public function map(Closure $closure): Set
    {
        $set = new Set();
        foreach ($this as $element) {
            $set->attach($closure($element));
        }
        return $set;
    }

    /**
     * @return Set<T>
     * A new set with each element cloned
     */
    public function deepCopy(): Set
    {
        return $this->map(
            /**
             * @param T $element
             * @return object
             * @suppress PhanTypePossiblyInvalidCloneNotObject phan does not support base types of template types yet.
             */
            static function ($element) {
                return clone($element);
            }
        );
    }

    /**
     * @param Closure(object):bool $closure
     * A closure that takes an element and returns a boolean
     * TODO: Make this be Closure(T):bool and read the types from the template
     *
     * @return T|false
     * The first element for which the given closure returns
     * true is returned or false if no elements pass the
     * given closure
     * @suppress PhanUnreferencedPublicMethod potentially useful but currently unused
     */
    public function find(Closure $closure)
    {
        foreach ($this as $element) {
            if ($closure($element)) {
                return $element;
            }
        }
        return false;
    }

    /**
     * @return string
     * A string representation of this set for use in
     * debugging
     */
    public function __toString(): string
    {
        return '['
            . \implode(',', \array_map('strval', \iterator_to_array($this)))
            . ']';
    }
}
