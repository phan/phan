<?php
declare(strict_types=1);

namespace NS661;

function DoubleInt(int $i) : int
{
    return $i * 2;
}

/**
 * @param ?int $i
 * @return ?int
 */
function DoubleIntOrNull($i)
{
    if (is_int($i)) {
        return DoubleInt($i);
    }
    return null;
}

class Example
{

    /** @var ?int */
    protected $i;

    /**
     * @param ?int $i
     */
    public function __construct($i)
    {
        $this->i = $i;
    }
    /** @return ?int */
    public function doubleMe()
    {
        if (is_int($this->i)) {
            return DoubleInt($this->i);
        }
        return DoubleInt($this->i);
    }
}

$ex = new Example(5);
echo $ex->doubleMe(), "\n";
