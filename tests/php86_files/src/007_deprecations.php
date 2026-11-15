<?php

namespace NS86Deprecations;

// Functions, methods, constants and names deprecated in PHP 8.6

function let(): void
{
}

function is(): void
{
}

function readonly(): void
{
}

class Let
{
    const NAMESPACE = 1;
    const OK = 2;

    // Methods with these names are not deprecated
    public function let(): int
    {
        return self::NAMESPACE + self::OK;
    }
}

interface Is
{
}

function test(float $f, \stdClass $o, array $a): void
{
    echo is_double($f), is_integer($f), is_long($f), doubleval('1.5');
    echo strcoll('a', 'b'), metaphone('Thompson');
    echo spl_object_hash($o), count(spl_classes());
    sort($a, \SORT_LOCALE_STRING);

    $it = new \ArrayIterator($a);
    $it->asort();
    $it->ksort();
    echo $it->getFlags(), $it->count();

    $file = new \SplFileObject('php://memory');
    $file->setCsvControl(',', '"', '');
    var_dump($file->fgetcsv(), $file->getCsvControl(), $file->fgets());
}

let();
is();
test(1.5, new \stdClass(), []);
echo (new Let())->let(), Is::class;
