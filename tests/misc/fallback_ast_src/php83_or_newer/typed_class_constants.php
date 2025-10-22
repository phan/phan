<?php

namespace Tests\TypedConst;

class Dependency {}

class Example {
    public const int|string UNION = 3;
    private const Dependency|int EITHER = 0;
}
