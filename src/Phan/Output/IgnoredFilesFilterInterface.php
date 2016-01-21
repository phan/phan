<?php declare(strict_types = 1);
namespace Phan\Output;

interface IgnoredFilesFilterInterface
{

    /**
     * @param string $filename
     * @return bool True if filename is ignored during analysis
     */
    public function isFilenameIgnored(string $filename):bool;
}
