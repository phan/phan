<?php
// A (void) cast in a for loop's expression list used to throw a NodeException.
function inc5963(): int { return 1; }
for ($i = 0; (void)inc5963(), $i < 3; $i++) {}

// The deprecation for (double) is emitted while parsing. Emitting it from inside the parser used to corrupt the
// line numbers of the closures that follow, which made Phan fail to find them during analysis.
(double) 1;
$f = fn() => 1;
echo $f();
