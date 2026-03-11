<?php

// Test that accessing properties on a template type variable doesn't emit
// PhanTypeExpectedObjectPropAccess (false positive for issue #5479).

class DemoItem {
    public int $type = 42;
}

class Demo {

    /**
     * @template T
     * @param class-string<T> $class
     * @param T[] $a
     * @return T|null
     */
    public function getItem(string $class, array $a) {
        // Should not emit PhanTypeExpectedObjectPropAccess — $i is template type T
        $matching = array_filter(
            $a,
            static fn($i) => $i->type === 42
        );
        return $matching ? reset($matching) : null;
    }

    /**
     * @template T of object
     * @param T[] $a
     * @return T|null
     */
    public function getItemBounded(array $a) {
        // Should not emit PhanTypeExpectedObjectPropAccess — $i is T bounded to object
        $matching = array_filter(
            $a,
            static fn($i) => $i->type === 42
        );
        return $matching ? reset($matching) : null;
    }

    public function getItem2(array $a) {
        // Should not emit PhanTypeExpectedObjectPropAccess — $i is mixed
        $matching = array_filter(
            $a,
            static fn($i) => $i->type === 42
        );
        return $matching ? reset($matching) : null;
    }
}
