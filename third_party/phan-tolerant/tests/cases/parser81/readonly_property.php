<?php
class X {
    public readonly int $var;
    #[SomeAttribute]
    public readonly ?stdClass $xyz;

    // NOTE: In php, this is a compilation error, not a parse error.
    public readonly function invalid() {
    }
}
