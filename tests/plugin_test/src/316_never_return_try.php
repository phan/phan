<?php

class PluginNeverReturnTry
{
    public function run(): void
    {
        $this->test('foo');
    }

    private function test(string $arg): void
    {
        try {
            $value = $arg;
        } catch (\RuntimeException $e) {
            $this->noreturn();
        }

        echo $value;
    }

    private function noreturn(): never
    {
        exit;
    }
}
