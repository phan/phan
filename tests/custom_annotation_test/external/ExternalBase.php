<?php

// This file is parsed but excluded from analysis: the copies of the methods of this class in subclasses are created lazily.
// (The issues that the plugins emit for this file in the parse phase are not reported.)
class ExternalBase
{
    /** @phan-x-test-mark on an external constant */
    public const EXTERNAL_CONSTANT = 1;

    /** @phan-x-test-mark on an external property */
    public $external_property = 1;

    /** @phan-x-test-mark on an external method */
    public function externalMethod(): void
    {
    }

    public function unmarkedExternalMethod(): void
    {
    }
}
