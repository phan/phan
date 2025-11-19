<?php

class TestErrorHandlerProp5370 {
    private static string $prop = '';

    public function doTest(): void {
        self::$prop = '';
        set_error_handler($this->errorHandler(...));
        trigger_error('example');
        restore_error_handler();
        if (self::$prop) {
            echo self::$prop;
        }
    }

    private function errorHandler(int $errno, string $errstr): bool {
        if ($errno === -1) {
            return false;
        }
        self::$prop .= $errstr;
        return true;
    }
}
