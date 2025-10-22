<?php

class PropertyHookExample
{
    private int $counter = 0;

    public int $value {
        get => $this->counter;
        set {
            $this->counter = $value;
        }
    }
}
