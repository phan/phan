<?php

#[\Deprecated]
trait LegacyBehavior
{
    public function run(): string
    {
        return 'legacy';
    }
}

class UsesLegacy
{
    use LegacyBehavior;
}

$legacy = new UsesLegacy();
echo $legacy->run();
