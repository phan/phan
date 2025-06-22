<?php

class PrivatePropsWithAV
{
    private public(set) string $prop1 = 'value';
    private protected(set) int $prop2 = 0;
    private private(set) ?string $prop3 = null;
}
