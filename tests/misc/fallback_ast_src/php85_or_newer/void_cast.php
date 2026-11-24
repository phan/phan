<?php
function f(): int { return 1; }
(void) f();
(void) [f(), f()];
(void) $x = f();
(void) f() + g();
(void) $a ?: $b;
for ((void) f(), (void) $i = 0; $i < 2; (void) g(), $i++) {}
