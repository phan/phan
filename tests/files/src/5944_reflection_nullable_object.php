<?php
// Regression test: the object argument of ReflectionProperty::setValue()/getValue() and
// ReflectionMethod::getClosure() may be null for static members (PHP's signatures take ?object).
class Holder5944 {
    private static ?int $cache = null;
    public int $value = 0;
    public static function make(): static {
        return new static();
    }
    public function get(): int {
        return $this->value;
    }
}

$p = new ReflectionProperty(Holder5944::class, 'cache');
$p->setValue(null, 5);
var_export($p->getValue(null));
var_export($p->getValue());
// The deprecated single-argument form for static properties is still accepted.
$p->setValue(6);

$q = new ReflectionProperty(Holder5944::class, 'value');
$holder = new Holder5944();
$q->setValue($holder, 7);
var_export($q->getValue($holder));
// Neither an object nor null: still reported.
$q->setValue('holder', 8);

$m = new ReflectionMethod(Holder5944::class, 'make');
var_export($m->getClosure(null));
var_export($m->getClosure());
$n = new ReflectionMethod(Holder5944::class, 'get');
var_export($n->getClosure($holder));
var_export($n->getClosure('holder'));
