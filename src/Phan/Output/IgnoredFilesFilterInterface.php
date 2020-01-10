<?php

declare(strict_types=1);

namespace Phan\Output;

/**
 * Used to check if a filename is ignored during analysis
 */
interface IgnoredFilesFilterInterface
{

    /**
     * @param string $filename
     * @return bool True if filename is ignored during analysis
     */
    public function isFilenameIgnored(string $filename): bool;
}
