<?php
// PHP refuses to compile writes to a nullsafe chain: "Can't use nullsafe operator in write context"
class C5954 {
    public $p;
    public array $items = [];
    public ?C5954 $child = null;
    public static $s;
    public function m(): C5954 { return $this; }
    public static function sm(): void {}
}
function test5954(?C5954 $c, string $k, iterable $it): void {
    unset($c?->p);
    unset($c?->items[$k]);
    unset($c?->child->p);
    unset($c->child?->p);
    unset($c?->m()->p);
    unset($c->m()?->items[$k]);
    unset($c?->child, $c?->p);

    $c?->p = 1;
    $c?->items[$k] = 1;
    $c?->child->items[$k]['x'] = 1;
    $c?->p = &$k;
    $c?->p .= 'x';
    $c?->items[$k] ??= 1;
    $c?->p++;
    --$c?->items[$k];
    $c?->p::$s = 1;
    [$c?->p, $c?->items[$k]] = [1, 2];
    list('a' => $c->child?->p) = ['a' => 1];
    foreach ($it as $c?->p) {}
    foreach ($it as $c?->p => $v) { echo $v; }
    foreach ($it as &$c?->items[$k]) {}

    // A bare call as the write target is a different compile error (method return value in write context)
    $c?->m() = 1;
    unset($c?->m());
    $c?->p::sm() = 1;

    // Reads and non-nullsafe writes are fine
    echo $c?->p, $c?->items[$k], $c?->m()->p;
    var_dump(isset($c?->items[$k]), empty($c?->child?->p));
    if ($c) {
        unset($c->items[$k]);
        $c->child->p = 1;
        $c->m()->items[$k] = 1;
    }
}
