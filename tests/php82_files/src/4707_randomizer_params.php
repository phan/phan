<?php
// Regression test: the PHP 8.2 signature delta listed these methods with their return type only,
// which made Phan believe they take no parameters (PhanParamTooManyInternal on every call).
function randomizer4707(array $values): array {
    $r = new Random\Randomizer(new Random\Engine\PcgOneseq128XslRr64(42));
    $shuffled = $r->shuffleArray($values);
    $bytes = $r->getBytes(8);
    $int = $r->getInt(1, 10);
    $keys = $r->pickArrayKeys($values, 2);
    $letters = $r->shuffleBytes('abcdef');
    $engine = new Random\Engine\PcgOneseq128XslRr64(1);
    $engine->jump(3);
    // Too many arguments are still reported.
    $r->shuffleArray($values, $values);
    return [$shuffled, $bytes, $int, $keys, $letters];
}
