<?php

declare(strict_types=1);

namespace Phan\Library;

use Closure;
// @phan-suppress-next-line PhanUnreferencedUseNormal
use ReturnTypeWillChange;
use SplObjectStorage;

/**
 * A map from object to object with key comparisons
 * based on spl_object_hash.
 *
 * @template K
 * @template V
 * @suppress PhanTemplateTypeNotDeclaredInFunctionParams
 * @phan-file-suppress PhanParamSignaturePHPDocMismatchHasParamType, PhanParamSignaturePHPDocMismatchParamType, PhanParamSignatureMismatchInternal
 * @phan-file-suppress PhanUndeclaredClassAttribute `ReturnTypeWillChange` is undeclared in php 8.0, tentative return types were added in 8.1
 * TODO: Add a way to indicate in Phan that T is subtype of object for keys K
 *
 * @method void attach(K $object,V $data = null)
 * @method void detach(K $object)
 * @method bool offsetExists(K $object)
 * @method V offsetGet(K $object )
 * @method void offsetSet(K $object,V $data = null)
 * @method void offsetUnset(K $object)
 */
class Map extends SplObjectStorage
{

    /**
     * We redefine the key to be the actual key rather than
     * the index of the key
     *
     * @return K
     * @suppress PhanParamSignatureMismatchInternal - This is deliberately changing the phpdoc return type.
     */
    #[ReturnTypeWillChange]
    public function key()
    {
        return parent::current();
    }

    /**
     * We redefine the current value to the current value rather
     * than the current key
     * @return V
     */
    #[ReturnTypeWillChange]
    public function current()
    {
        return $this->getInfo();
    }

    /**
     * @template KNew
     * @template VNew
     * @param Closure(object):KNew $key_closure
     * A closure that maps each key of this map
     * to a new key
     *
     * @param Closure(object):VNew $value_closure
     * A closure that maps each value of this map
     * to a new value.
     *
     * @return Map<KNew,VNew>
     * A new map containing the mapped keys and
     * values
     */
    public function keyValueMap(Closure $key_closure, Closure $value_closure): Map
    {
        $map = new Map();
        foreach ($this as $key => $value) {
            // TODO: Don't infer $map[$key] = ...; as making $map possibly an array
            $map->offsetSet($key_closure($key), $value_closure($value));
        }
        return $map;
    }

    /**
     * @return Map<K,V>
     * A new map with each key and value cloned
     * @suppress PhanUnreferencedPublicMethod possibly useful but currently unused
     */
    public function deepCopy(): Map
    {
        $clone =
            /**
             * @param K|V $element
             * @return K|V
             * @suppress PhanTypePossiblyInvalidCloneNotObject phan does not support base types of template types yet.
             */
            static function ($element) {
                return clone($element);
            };
        return $this->keyValueMap($clone, $clone);
    }

    /**
     * @return Map<K,V>
     * A new map with each value cloned (keys remain uncloned)
     */
    public function deepCopyValues(): Map
    {
        $map = new Map();
        foreach ($this as $key => $value) {
            $map->offsetSet($key, clone($value));
        }
        return $map;
    }

    /**
     * @return Set<V>
     * A new set with the unique values from this map.
     * Precondition: values of this map are objects.
     * @suppress PhanUnreferencedPublicMethod possibly useful but currently unused
     */
    public function valueSet(): Set
    {
        $set = new Set();
        foreach ($this as $value) {
            $set->attach($value);
        }
        return $set;
    }

    /**
     * @return Set<K>
     * A new set with the unique keys from this map.
     * Precondition: values of this set are objects.
     * @suppress PhanUnreferencedPublicMethod possibly useful but currently unused
     */
    public function keySet(): Set
    {
        $set = new Set();
        foreach ($this as $key => $_) {
            $set->attach($key);
        }
        return $set;
    }
}
