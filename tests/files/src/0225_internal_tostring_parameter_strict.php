<?php declare(strict_types=1);
try {
    throw new Exception("message");
} catch(\Exception $e) {
    error_log($e);
}
