<?php

// Asymmetric visibility modifiers of trait properties must not be mistaken for Phan's own flags.
trait NamedTrait
{
    public protected(set) string $name = 'a';
    public private(set) string $label = 'b';

    public function rename(string $name): void
    {
        $this->name = $name;
        $this->label = $name;
    }
}

class Named
{
    use NamedTrait;
}

$named = new Named();
$named->rename("c");
echo $named->name, $named->label;
