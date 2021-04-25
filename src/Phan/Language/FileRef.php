<?php

declare(strict_types=1);

namespace Phan\Language;

use Phan\Config;

/**
 * An object representing the context in which any
 * structural element (such as a class or method) lives.
 */
class FileRef implements \Serializable
{

    /**
     * @var string
     * The path to the file in which this element is defined
     */
    protected $file = 'internal';

    /**
     * @var int
     * The starting line number of the element within the $file
     */
    protected $line_number_start = 0;

    /**
     * @var int
     * The ending line number of the element within the $file
     */
    protected $line_number_end = 0;

    /**
     * @param string $file
     * The path to the file in which this element is defined
     *
     * @return static
     * This context with the given file is returned
     */
    public function withFile(string $file)
    {
        $context = clone($this);
        $context->file = $file;
        return $context;
    }

    /**
     * @return string
     * The path to the file in which the element is defined
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * @return string
     * The path of this FileRef's file relative to the project
     * root directory
     */
    public function getProjectRelativePath(): string
    {
        return self::getProjectRelativePathForPath($this->file);
    }

    /**
     * @param string $cwd_relative_path (relative or absolute path)
     * @return string
     * The path of the file relative to the project root directory for the provided path
     *
     * @see Config::getProjectRootDirectory() for converting paths to absolute paths
     */
    public static function getProjectRelativePathForPath(string $cwd_relative_path): string
    {
        if ($cwd_relative_path === '') {
            return '';
        }
        // Get a path relative to the project root
        // e.g. if the path is /my-project, then strip the beginning of "/my-project/src/a.php" to "src/a.php" but should not change /my-project-unrelated-src/a.php
        // And don't strip subdirectories of the same name, e.g. should convert "/my-project/subdir/my-project/file.php" to "subdir/my-project/file.php"
        // And convert "/my-project/.//src/a.php" to "src/a.php"
        $path = \realpath($cwd_relative_path) ?: $cwd_relative_path;
        $root_directory = Config::getProjectRootDirectory();
        $n = \strlen($root_directory);
        if (\strncmp($path, $root_directory, $n) === 0) {
            if (\in_array($path[$n] ?? '', [\DIRECTORY_SEPARATOR, '/'], true)) {
                $path = (string)\substr($path, $n + 1);
                // Strip any extra beginning directory separators
                $path = \ltrim($path, '/' . \DIRECTORY_SEPARATOR);
                return $path;
            }
        }

        // Deal with a wide variety of cases
        // E.g. the project in question is a symlink,
        // or uses directory separators that were converted to Windows directory by the call to realpath.
        // (On Windows, 'c:/Project/Xyz/./other' gets normalized to 'C:\Project\Xyz\other' (uppercase drive letter))
        $root_directory_realpath = (string)\realpath($root_directory);
        if ($root_directory_realpath !== '' && $root_directory_realpath !== $root_directory) {
            $n = \strlen($root_directory_realpath);
            if (\strncmp($path, $root_directory_realpath, $n) === 0) {
                if (\in_array($path[$n] ?? '', [\DIRECTORY_SEPARATOR, '/'], true)) {
                    $path = (string)\substr($path, $n + 1);
                    // Strip any extra beginning directory separators
                    $path = \ltrim($path, '/' . \DIRECTORY_SEPARATOR);
                    return $path;
                }
            }
        }

        return $path;
    }

    /**
     * @return bool
     * True if this object is internal to PHP
     */
    public function isPHPInternal(): bool
    {
        return 'internal' === $this->file;
    }

    /**
     * @return bool
     * True if this object refers to the same file and line number.
     */
    public function equals(FileRef $other): bool
    {
        return $this->line_number_start === $other->line_number_start && $this->file === $other->file;
    }

    /**
     * @param int $line_number
     * The starting line number of the element within the file
     *
     * @return static
     * This context with the given line number is returned
     */
    public function withLineNumberStart(int $line_number)
    {
        $this->line_number_start = $line_number;
        return $this;
    }

    /**
     * @param int $line_number
     * The starting line number of the element within the file
     *
     * @return void
     * Both this and withLineNumberStart modify the original context.
     */
    public function setLineNumberStart(int $line_number): void
    {
        $this->line_number_start = $line_number;
    }

    /**
     * @return int
     * The starting line number of the element within the file
     */
    public function getLineNumberStart(): int
    {
        return $this->line_number_start;
    }

    /**
     * @param int $line_number
     * The ending line number of the element within the $file
     *
     * @return static
     * This context with the given end line number is returned
     */
    public function withLineNumberEnd(int $line_number)
    {
        $this->line_number_end = $line_number;
        return $this;
    }

    /**
     * Get a string representation of the context
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->file . ':' . $this->line_number_start;
    }

    public function serialize(): string
    {
        return \serialize($this->__serialize());
    }

    /**
     * @param string $serialized
     */
    public function unserialize($serialized): void
    {
        $this->__unserialize(\unserialize($serialized));
    }

    /**
     * @return array{0:string, 1:int, 2:int}
     */
    public function __serialize(): array
    {
        return [$this->file, $this->line_number_start, $this->line_number_end];
    }

    /**
     * @param array{0:string, 1:int, 2:int} $data
     */
    public function __unserialize(array $data): void
    {
        [$this->file, $this->line_number_start, $this->line_number_end] = $data;
    }

    /**
     * @param FileRef $other - An instance of FileRef or a subclass such as Context
     * @return FileRef - A plain file ref, with no other information
     */
    public static function copyFileRef(FileRef $other): FileRef
    {
        $file_ref = new FileRef();
        $file_ref->file = $other->file;
        $file_ref->line_number_start = $other->line_number_start;
        $file_ref->line_number_end = $other->line_number_end;
        return $file_ref;
    }
}
