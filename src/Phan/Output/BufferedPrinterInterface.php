<?php declare(strict_types = 1);
namespace Phan\Output;

interface BufferedPrinterInterface extends IssuePrinterInterface
{
    /** flush printer buffer */
    public function flush();
}
