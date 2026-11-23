<?php
function f(): int { return 1; }
(void) f();
(void) [f(), f()];
