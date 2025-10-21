<?php
// FIXME the tolerant-php-parser has a bug, this code always echoes if executed.
// (i.e. same as `if (false); echo "hello world"` with an implicit semicolon)
// NOTE: If the inline HTML is surrounded by brackets, then this would never echo.
if (false)?>hello world<?php

if (false)
    echo "A";
elseif (false)?>Hello, World<?php

if (true)
    echo "\ntrue";
else?>After else<?php
