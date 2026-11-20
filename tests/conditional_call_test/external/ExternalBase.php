<?php

// This file is parsed but excluded from analysis: the copies of the methods of this class in subclasses are created lazily.
class ExternalBase
{
    public function externalVerify(int|string $x): bool
    {
        return is_string($x);
    }
}
