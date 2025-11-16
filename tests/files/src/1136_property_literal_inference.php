<?php

class TestPropTypeInferenceIssue5360 {
    public bool $prop1 = false;
    /** @var string */
    public $prop2 = '';

    private function doTest(): void
    {
        if (!$this->prop1) {
            if ($this->prop2 === 'foo') {
                $this->prop1 = true;
            }
        }

        if ($this->prop1) {
            // Intentionally empty block
        }

        if ($this->prop2 === 'foo') {
            echo 'x';
        }
    }
}
