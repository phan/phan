#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Utility to delete lines from a file.
 *
 * Potentially useful for issue types that don't have an automatic fixer
 * but correspond to a single line of code that can be deleted.
 * (in pylint or plaintext output formats)
 */
class LineDeleter
{
    private static function printUsage(): void
    {
        global $argv;
        fwrite(
            STDERR,
            <<<EOT
Usage: {$argv[0]} file_with_lines_to_delete.txt

Accepts a path a file with lines of the form:
absolute_or_relative/path/to/file.php:<lineno> error message goes here

Modifies the files in place to delete those lines.

EOT
        );
    }

    // Deliberately duplicates StringUtil to work as a standalone script.
    private static function jsonEncode(string $value): string
    {
        $result = json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PARTIAL_OUTPUT_ON_ERROR);
        return is_string($result) ? $result : '(invalid data)';
    }

    /**
     * Gets the set of lines to delete from various files.
     * @return array<string, array<int, int>>
     */
    public static function getLinesToDelete(string $file_contents): array
    {
        $paths = [];
        foreach (explode("\n", $file_contents) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // Assume files start with '<file>:<line> ' and file paths don't have whitespace as a sanity check for bad inputs
            if (!preg_match('/^(\S+):([0-9]+)\s/', $line, $matches)) {
                // Give up if the
                throw new InvalidArgumentException("Refusing to delete files: Saw unexpected line " . self::jsonEncode($line));
            }
            $affected_path = $matches[1];
            $lineno = (int)$matches[2];
            // Use realpath in case multiple symlinks to the same path exist.
            $paths[realpath($affected_path)][$lineno] = $lineno;
        }
        foreach ($paths as $affected_path => $_) {
            if (!file_exists((string)$affected_path)) {
                throw new InvalidArgumentException("Refusing to delete files: Saw missing file " . self::jsonEncode($affected_path));
            }
        }
        return $paths;
    }

    /**
     * Deletes the provided set of line numbers from the affected file path
     * @param array<int, int> $line_set
     */
    public static function deleteLinesFromFile(string $affected_file_path, array $line_set): void
    {
        $file_contents = file_get_contents($affected_file_path);
        if (!is_string($file_contents)) {
            fprintf(STDERR, "Could not read %s, skipping\n", self::jsonEncode($affected_file_path));
            return;
        }
        $lines = explode("\n", $file_contents);
        $actual_line_count = count($lines);
        foreach ($line_set as $lineno) {
            if (!isset($lines[$lineno - 1])) {
                throw new InvalidArgumentException("Tried to delete line $lineno from file $affected_file_path with only $actual_line_count lines");
            }
            unset($lines[$lineno - 1]);
        }
        fprintf(STDERR, "Saving line deletions to %s\n", self::jsonEncode($affected_file_path));
        file_put_contents($affected_file_path, implode("\n", $lines));
    }

    /**
     * Deletes lines from various files
     * @param array<string, array<int, int>> $lines_to_delete the set of lines to delete from various files.
     */
    private static function deleteLines(array $lines_to_delete): void
    {
        foreach ($lines_to_delete as $affected_file_path => $line_set) {
            self::deleteLinesFromFile((string) $affected_file_path, $line_set);
        }
    }

    /**
     * The line_deleter script implementation.
     * Reads a path to a file with files and line numbers to delete, and deletes those lines from those files.
     */
    public static function main(): void
    {
        global $argv;
        if (count($argv) !== 2 || in_array($argv[1], ['-h', 'help', '--help'], true)) {
            self::printUsage();
            exit(0);
        }
        $file_path = $argv[1];
        if (!is_file($file_path)) {
            fprintf(STDERR, "Could not find text file %s\n", self::jsonEncode($file_path));
            self::printUsage();
            exit(1);
        }
        $file_contents = file_get_contents($file_path);
        if (!is_string($file_contents)) {
            fprintf(STDERR, "Could not read text file %s\n", self::jsonEncode($file_path));
            self::printUsage();
            exit(1);
        }
        $lines_to_delete = self::getLinesToDelete($file_contents);
        self::deleteLines($lines_to_delete);
    }
}
LineDeleter::main();
