<?php

// Test that property type narrowing works for parameter object properties

/**
 * @phan-file-suppress PhanNoopNewNoSideEffects
 */

class Item
{
    public function __construct(public ?int $v = null)
    {
    }
}

class Handler
{
    public function do(Item $item)
    {
        // Case 1: Parameter property narrowing with !== null
        if (null !== $item->v) {
            $this->doSomethingWithItemValue($item->v); // Should NOT warn
        }
    }

    public function do2(Item $item)
    {
        // Case 2: Parameter property narrowing with === null (negated)
        if ($item->v === null) {
            return;
        }
        $this->doSomethingWithItemValue($item->v); // Should NOT warn
    }

    public function do3(Item $item)
    {
        // Case 3: is_int check
        if (is_int($item->v)) {
            $this->doSomethingWithItemValue($item->v); // Should NOT warn
        }
    }

    public function do4(Item $item)
    {
        // Case 4: Local variable property narrowing
        $obj = new Item(5);
        if (null !== $obj->v) {
            $this->doSomethingWithItemValue($obj->v); // Should NOT warn
        }
    }

    public function doSomethingWithItemValue(int $v): void
    {
        var_dump($v);
    }
}

class ThisPropTest
{
    public ?string $value = null;

    public function test()
    {
        // Case 5: $this->prop narrowing (existing behavior, should still work)
        if (null !== $this->value) {
            $this->acceptString($this->value); // Should NOT warn
        }
    }

    public function acceptString(string $s): void
    {
        var_dump($s);
    }
}

// Instantiate to trigger analysis
$h = new Handler();
$item = new Item(10);
$h->do($item);
$h->do2($item);
$h->do3($item);
$h->do4($item);
$t = new ThisPropTest();
$t->value = "test";
$t->test();
