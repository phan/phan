<?php
try {
    throw new Exception("message");
} catch(\Exception $e) {
    error_log($e);
}
