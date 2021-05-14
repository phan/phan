<?php
declare(strict_types=1);
echo intdiv(fsync(123), 2);  // should warn about types
