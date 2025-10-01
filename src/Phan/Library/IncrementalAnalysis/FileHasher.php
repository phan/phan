<?php

declare(strict_types=1);

namespace Phan\Library\IncrementalAnalysis;

use function file_get_contents;
use function filemtime;
use function filesize;
use function hash;

/**
 * Utilities for hashing file contents.
 *
 * This class has ZERO dependencies on Phan core.
 */
class FileHasher
{
    /**
     * Compute SHA-256 hash of file contents
     *
     * @param string $file_path Absolute path to file
     * @return string Hash in format "sha256:hexdigest", or empty string on error
     */
    public static function hashFile(string $file_path): string
    {
        $contents = file_get_contents($file_path);
        if ($contents === false) {
            return '';
        }
        return 'sha256:' . hash('sha256', $contents);
    }

    /**
     * Get file size in bytes
     *
     * @param string $file_path Absolute path to file
     * @return int Size in bytes, or 0 on error
     */
    public static function getFileSize(string $file_path): int
    {
        $size = filesize($file_path);
        return $size !== false ? $size : 0;
    }

    /**
     * Get file modification time
     *
     * @param string $file_path Absolute path to file
     * @return int Unix timestamp, or 0 on error
     */
    public static function getFileMtime(string $file_path): int
    {
        $mtime = filemtime($file_path);
        return $mtime !== false ? $mtime : 0;
    }

    /**
     * Get all file metadata at once
     *
     * @param string $file_path Absolute path to file
     * @return array{hash:string,size:int,mtime:int}
     */
    public static function getFileMetadata(string $file_path): array
    {
        return [
            'hash' => self::hashFile($file_path),
            'size' => self::getFileSize($file_path),
            'mtime' => self::getFileMtime($file_path),
        ];
    }
}
