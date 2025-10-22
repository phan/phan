<?php

class PropertyHookExample
{
    private int $counter = 0;

    public int $value {
        get => $this->counter;
        set(int $value) {
            $this->counter = $value;
        }
    }
}
