<?php

// Test for https://github.com/phan/phan/issues/5569
// A nullsafe access that is known to have evaluated to a non-null value implies its receiver is non-null.

class A5937 {
    public bool $prop = true;
    public ?A5937 $next = null;
    /** @var array<string,int> */
    public array $data = [];
    public function m(): bool {
        return true;
    }
    public function name(): string {
        return 'a';
    }
    public function maybe(): ?A5937 {
        return null;
    }
}

function takesA5937(A5937 $a): void {
}

function v1(?A5937 $x): void {
    if ($x?->m()) {
        takesA5937($x);  // should not warn
    }
    takesA5937($x);  // should warn
}

function v2(?A5937 $x): void {
    if ($x?->prop) {
        takesA5937($x);  // should not warn
    }
}

function v3(?A5937 $x): void {
    if ($x?->m() === true) {
        takesA5937($x);  // should not warn
    }
    if (true === $x?->m()) {
        takesA5937($x);  // should not warn
    }
    if ($x?->name() === 'a') {
        takesA5937($x);  // should not warn
    }
    if ($x?->name() == 'a') {
        takesA5937($x);  // should not warn
    }
    if ($x?->m() !== null) {
        takesA5937($x);  // should not warn
    }
    if ($x?->m() != null) {
        takesA5937($x);  // should not warn
    }
    if ($x?->m() != false) {
        takesA5937($x);  // should not warn
    }
    if ($x?->m() === null) {
        takesA5937($x);  // should warn
    } else {
        takesA5937($x);  // should not warn
    }
    if ($x?->m() == 0) {
        takesA5937($x);  // should warn - null == 0
    }
    if ($x?->name() != '0') {
        takesA5937($x);  // should warn - null != '0'
    }
    if ($x?->m() == true) {
        takesA5937($x);  // should not warn
    }
}

function v5(?A5937 $x): void {
    if (!$x?->m()) {
        return;
    }
    takesA5937($x);  // should not warn
}

function v6(?A5937 $x): void {
    if (!$x?->m()) {
        takesA5937($x);  // should warn - $x may be null
    }
}

function v7(?A5937 $x): void {
    if (isset($x?->prop)) {
        takesA5937($x);  // should not warn
    }
    if (isset($x?->data['key'])) {
        takesA5937($x);  // should not warn
    }
}

function v8(?A5937 $x): void {
    if ($x?->next?->m()) {
        takesA5937($x);  // should not warn
        takesA5937($x->next);  // should not warn
    }
    if ($x?->maybe()->m()) {
        takesA5937($x);  // should not warn
    }
    if ($x?->maybe() instanceof A5937) {
        takesA5937($x);  // should not warn
    }
}

function v9(?A5937 $x): void {
    while ($x?->m()) {
        takesA5937($x);  // should not warn
        $x = $x->next;
    }
    $x?->m() ? takesA5937($x) : null;  // should not warn
    if ($x?->m() && rand()) {
        takesA5937($x);  // should not warn
    }
}

class Holder5937 {
    public ?A5937 $a = null;
    public function check(): void {
        if ($this->a?->m()) {
            takesA5937($this->a);  // should not warn
        }
        takesA5937($this->a);  // should warn
    }
}
