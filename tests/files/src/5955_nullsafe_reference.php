<?php
// PHP refuses to compile a reference to a nullsafe chain: "Cannot take reference of a nullsafe chain"
class C5955 {
    public $p;
    public array $items = [];
    public ?C5955 $child = null;
    public function m(): C5955 { return $this; }
}
function test5955(?C5955 $c, string $k): void {
    $a = &$c?->p;
    $b = &$c?->items[$k];
    $d = &$c?->m()->p;
    $e = &$c?->m();
    $f = &$c->m()?->p;
    $g = &$c?->child->items;
    var_dump($a, $b, $d, $e, $f, $g);

    // Fine: reads, and references to non-nullsafe chains
    $h = $c?->p;
    $i = &$c->p;
    $j = &$c->items[$k];
    var_dump($h, $i, $j);
}
