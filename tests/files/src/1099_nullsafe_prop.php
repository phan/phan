<?php

class Em {
    public int $i;
    public ?Em $em;
    public function getEm(): ?Em
    {
        return $this?->em;
    }
}

function test26(?Em $x) : stdClass {
    $y = null;
    echo $y?->em;

    echo $x?->em;  // valid prop access, invalid echo
    echo strlen($x?->i);  // ?int
    var_dump($x?->undeclaredProp);
    return $x?->getEm()?->i;
}
// TODO: Warn about unnecessary uses of ?-> on non-null expressions
