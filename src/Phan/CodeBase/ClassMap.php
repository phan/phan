<?php

declare(strict_types=1);

namespace Phan\CodeBase;

use Phan\Analysis\LazyInheritedMethods;
use Phan\Language\Element\ClassConstant;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;

/**
 * Maps for elements associated with an individual class
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */
class ClassMap
{
    /**
     * @var array<string,ClassConstant>
     * A map from name to ClassConstant
     */
    private $class_constant_map = [];

    /**
     * @var array<string,Property>
     * A map from name to Property
     */
    private $property_map = [];

    /**
     * @var array<string,Method|InheritedMethodLink>
     * A map from the lowercase name (with the alternate id suffix ",<id>" if non-zero) to the Method,
     * or to the InheritedMethodLink of a method whose inherited copy is created when first looked up (LazyInheritedMethods)
     */
    private $method_map = [];

    /** @var int the number of InheritedMethodLink values in $method_map (if 0, every accessor returns Methods without extra work) */
    private $lazy_entry_count = 0;

    /**
     * @var bool true once an InheritedMethodLink resolves through this map (its $parent_map is this map):
     * changes of slots then record the previous value (see entryAt())
     */
    private $is_link_target = false;

    /**
     * @var array<string,list<array{0:int,1:Method|InheritedMethodLink}>>
     * for a link target, by class map key: [sequence number of a change of the slot, value the slot held before it], ascending
     */
    private $slot_history = [];

    /** @var ?LazyInheritedMethods the registry of the lazy entries of this map or of the links resolving through it */
    private $registry = null;

    /**
     * @var array<int,int> for a link target: the sequence numbers of the links that resolve through this map
     * and may still need the slot history => the number of such links (for pruning the history)
     */
    private $dependent_seqs = [];

    /**
     * Record that the class this represents has the provided class constant.
     */
    public function addClassConstant(ClassConstant $constant): void
    {
        $this->class_constant_map[
            $constant->getFQSEN()->getNameWithAlternateId()
        ] = $constant;
    }

    /**
     * @return bool does this class have a constant with name $name
     */
    public function hasClassConstantWithName(string $name): bool
    {
        return isset($this->class_constant_map[$name]);
    }

    /**
     * Gets the class constant $name of this class
     */
    public function getClassConstantByName(string $name): ClassConstant
    {
        return $this->class_constant_map[$name];
    }

    /**
     * @return array<string,ClassConstant>
     */
    public function getClassConstantMap(): array
    {
        return $this->class_constant_map;
    }

    /**
     * Record that the class this represents has the provided property information
     */
    public function addProperty(Property $property): void
    {
        $this->property_map[
            $property->getFQSEN()->getNameWithAlternateId()
        ] = $property;
    }

    /**
     * Checks if the class this represents has a property with name $name
     */
    public function hasPropertyWithName(string $name): bool
    {
        return isset($this->property_map[$name]);
    }

    /**
     * Fetch information about the property (of the class this represents) with name $name
     */
    public function getPropertyByName(string $name): Property
    {
        return $this->property_map[$name];
    }

    /**
     * @return array<string,Property>
     */
    public function getPropertyMap(): array
    {
        return $this->property_map;
    }

    /**
     * Records that the class that this represents has the provided method.
     */
    public function addMethod(Method $method): void
    {
        $key = \strtolower(
            $method->getFQSEN()->getNameWithAlternateId()
        );
        if ($this->registry !== null && isset($this->method_map[$key])) {
            // (Clazz::addMethod() looks up an existing method before replacing it, which materializes a lazy entry)
            $old = $this->method_map[$key];
            $this->recordSlotChange($key, $old);
            if ($old instanceof InheritedMethodLink) {
                // @phan-suppress-next-line PhanAccessMethodInternal
                $old->registry->discardLazySlot($old, $key);
            }
        }
        $this->method_map[$key] = $method;
    }

    /**
     * Checks if the class that this represents has a method with name $name.
     */
    public function hasMethodWithName(string $name): bool
    {
        return isset($this->method_map[\strtolower($name)]);
    }

    /**
     * Fetches the method signature with name $name of the class that this represents.
     * (This creates the copy of a lazily inherited method)
     */
    public function getMethodByName(string $name): Method
    {
        $lowercase_name = \strtolower($name);
        $method = $this->method_map[$lowercase_name];
        if ($method instanceof Method) {
            return $method;
        }
        // @phan-suppress-next-line PhanAccessMethodInternal
        return $method->registry->materialize($this, $lowercase_name, $method, 'lookup');
    }

    /**
     * Fetches the method with the lowercase name $lowercase_name (including any alternate id suffix), if it exists.
     * (This creates the copy of a lazily inherited method)
     */
    public function getMethodByLowercaseName(string $lowercase_name): ?Method
    {
        $method = $this->method_map[$lowercase_name] ?? null;
        if ($method === null || $method instanceof Method) {
            return $method;
        }
        // @phan-suppress-next-line PhanAccessMethodInternal
        return $method->registry->materialize($this, $lowercase_name, $method, 'lookup');
    }

    /**
     * Returns the methods of this class, by lowercase name.
     * (This creates the copies of all lazily inherited methods of this class)
     *
     * @return array<string,Method>
     */
    public function getMethodMap(): array
    {
        if ($this->lazy_entry_count > 0 && $this->registry !== null) {
            // @phan-suppress-next-line PhanAccessMethodInternal
            $this->registry->materializeAllOf($this, 'class_map');
        }
        // @phan-suppress-next-line PhanPartialTypeMismatchReturn there are no lazy entries left
        return $this->method_map;
    }

    // The entry API below is used by Phan's own code that must not cause the creation of method copies.
    // An entry is either a Method or an InheritedMethodLink (a method inherited from the parent class whose copy
    // is created on the first lookup by name, see LazyInheritedMethods); the entry API never creates those copies.

    /**
     * Returns the entry for the lowercase method name $lowercase_name (including any alternate id suffix), if any.
     * This never materializes a lazy entry.
     *
     * @internal
     */
    public function entry(string $lowercase_name): Method|InheritedMethodLink|null
    {
        return $this->method_map[$lowercase_name] ?? null;
    }

    /**
     * Returns the value that the slot $lowercase_name had at the sequence number $seq (CodeBase::nextLazySeq()),
     * or null if it had none. Slots only change after they were created when a lazy entry is materialized
     * or a method is replaced, so this is the current value unless the slot changed after $seq.
     *
     * @internal
     */
    public function entryAt(string $lowercase_name, int $seq): Method|InheritedMethodLink|null
    {
        foreach ($this->slot_history[$lowercase_name] ?? [] as [$until_seq, $value]) {
            if ($until_seq > $seq) {
                return $value;
            }
        }
        return $this->method_map[$lowercase_name] ?? null;
    }

    /**
     * Returns the entries of this class's methods, by lowercase name (including any alternate id suffix),
     * in insertion order. This never materializes lazy entries.
     *
     * @return array<string,Method|InheritedMethodLink>
     * @internal
     */
    public function getMethodEntryMap(): array
    {
        return $this->method_map;
    }

    /**
     * Returns the lowercase names (including any alternate id suffix) of this class's methods, in insertion order.
     * This never materializes lazy entries.
     *
     * @return list<string>
     * @internal
     */
    public function getMethodNames(): array
    {
        return \array_keys($this->method_map);
    }

    /**
     * Returns whether any entry of this map is a lazy entry.
     *
     * @internal
     */
    public function hasLazyEntries(): bool
    {
        return $this->lazy_entry_count > 0;
    }

    /**
     * Returns the number of lazy entries of this map.
     *
     * @internal
     */
    public function getLazyEntryCount(): int
    {
        return $this->lazy_entry_count;
    }

    /**
     * Adds a lazy entry for the method that $link's class inherits under the class map key $lowercase_name
     * (at the position where Clazz::addMethod() would have added the copy).
     *
     * @internal
     */
    public function addLazyEntry(string $lowercase_name, InheritedMethodLink $link): void
    {
        $this->registry = $link->registry;
        $this->method_map[$lowercase_name] = $link;
        $this->lazy_entry_count++;
    }

    /**
     * Replaces the value of the slot $lowercase_name (a lazy entry that was materialized) by $method,
     * keeping its position.
     *
     * @internal
     */
    public function replaceEntry(string $lowercase_name, Method $method): void
    {
        $old = $this->method_map[$lowercase_name] ?? null;
        if ($old !== null) {
            $this->recordSlotChange($lowercase_name, $old);
        }
        $this->method_map[$lowercase_name] = $method;
    }

    /**
     * Records that the slot $lowercase_name held $old until now (for entryAt(), if links resolve through this map),
     * and updates the number of lazy entries if $old was one.
     *
     */
    private function recordSlotChange(string $lowercase_name, Method|InheritedMethodLink $old): void
    {
        if ($old instanceof InheritedMethodLink) {
            $this->lazy_entry_count--;
        }
        if ($this->is_link_target && $this->dependent_seqs) {
            // @phan-suppress-next-line PhanAccessMethodInternal, PhanPossiblyNonClassMethodCall set by addDependentLink()
            $this->slot_history[$lowercase_name][] = [$this->registry->nextSlotChangeSeq(), $old];
            if ($old instanceof InheritedMethodLink) {
                $old->held_by_history++;
            }
        }
    }

    /**
     * Records that $link resolves through this map (it is $link->parent_map).
     *
     * @internal
     */
    public function addDependentLink(InheritedMethodLink $link): void
    {
        $this->is_link_target = true;
        $this->registry ??= $link->registry;
        $this->dependent_seqs[$link->seq] = ($this->dependent_seqs[$link->seq] ?? 0) + 1;
    }

    /**
     * Records that $link, which resolves through this map, no longer needs the slot history
     * (it has no lazy slot left and no history record holds it), and drops the history records that
     * no remaining dependent link needs.
     *
     * @return list<InheritedMethodLink> the links held by the dropped records that may now be released as well
     * @internal
     */
    public function removeDependentLink(InheritedMethodLink $link): array
    {
        $seq = $link->seq;
        $count = ($this->dependent_seqs[$seq] ?? 0) - 1;
        if ($count > 0) {
            $this->dependent_seqs[$seq] = $count;
            return [];
        }
        unset($this->dependent_seqs[$seq]);
        if (!$this->slot_history) {
            return [];
        }
        // A record [$until, $value] is only read for links with a sequence number below $until.
        $min_seq = $this->dependent_seqs ? \min(\array_keys($this->dependent_seqs)) : \PHP_INT_MAX;
        $released = [];
        foreach ($this->slot_history as $lowercase_name => $records) {
            $kept = [];
            foreach ($records as $record) {
                if ($record[0] <= $min_seq) {
                    $value = $record[1];
                    if ($value instanceof InheritedMethodLink) {
                        $value->held_by_history--;
                        $released[] = $value;
                    }
                    // @phan-suppress-next-line PhanAccessMethodInternal
                    $this->registry?->countHistoryPruned();
                    continue;
                }
                $kept[] = $record;
            }
            if ($kept) {
                $this->slot_history[$lowercase_name] = $kept;
            } else {
                unset($this->slot_history[$lowercase_name]);
            }
        }
        return $released;
    }

    /**
     * Returns the number of slot history records of this map (for counters and tests).
     *
     * @internal
     */
    public function getSlotHistoryRecordCount(): int
    {
        $count = 0;
        foreach ($this->slot_history as $records) {
            $count += \count($records);
        }
        return $count;
    }
}
