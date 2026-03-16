<?php

class BaseToString {
    public function __toString(): string
    {
        return 'base';
    }
}

class DerivedToString extends BaseToString
{
    public function __toString()
    {
        // Implicit string return; prior to the analyzer fix this triggered
        // PhanParamSignatureRealMismatchReturnTypeInternal noise.
        return 'derived';
    }
}

echo new DerivedToString();

