<?php

class ProtectedPropsWithAV
{
    protected public(set) string $prop1 = 'value';
    protected protected(set) int $prop2 = 0;
    protected private(set) ?string $prop3 = null;
}
