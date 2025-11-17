<?php

class StaticPropErrorHandlerTest {
    private static string $prop = '';

    public function run(): void {
        self::$prop = '';
        set_error_handler($this->handleError(...));
        $this->doSomethingThatMayTriggerAnError();
        if (self::$prop) {
            echo "error";
        }
    }

    private function doSomethingThatMayTriggerAnError(): void {
        // intentionally blank
    }

    private function handleError(int $errno, string $errstr): bool {
        self::$prop = $errstr;
        return $errno >= 0;
    }
}
