<?php
abstract class V002Base
{
    abstract public function compute(int $x): int;
    public function describe(): string { return 'x'; }
}
