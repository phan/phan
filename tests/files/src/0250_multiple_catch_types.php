<?php
try {
} catch (Exception|Throwable $exception) {
    print "{$exception->getMessage()}\n";
}
try {
} catch (Exception|Undef|Throwable $exception) {
    print "{$exception->getMessage()}\n";
}
