<?php declare(strict_types=1);
try {
    throw new Exception("message");
} catch(\Exception $e225) {
    error_log($e225);
}
