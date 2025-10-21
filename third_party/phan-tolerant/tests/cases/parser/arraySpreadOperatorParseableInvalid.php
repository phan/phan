<?php
// Can be parsed, but causes "Fatal error: Spread operator is not supported in assignments"
foreach ([[1]] as [...$x]) {}
list(...$x) = [1, 2];
