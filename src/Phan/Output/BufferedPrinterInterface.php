<?php declare(strict_types = 1);
namespace Phan\Output;

interface BufferedPrinterInterface extends IssuePrinterInterface
{
    /**
     * flush the printer buffer
     * @return void
     */
    public function flush();
}
