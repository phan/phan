<?php

/**
 * Fixers for --automatic-fix and WhitespacePlugin
 */

declare(strict_types=1);

use Phan\CodeBase;
use Phan\Config;
use Phan\IssueInstance;
use Phan\Library\FileCacheEntry;
use Phan\Plugin\Internal\IssueFixingPlugin\FileEdit;
use Phan\Plugin\Internal\IssueFixingPlugin\FileEditSet;
use Phan\Plugin\Internal\IssueFixingPlugin\IssueFixer;

return [
    /**
     * @return ?FileEditSet
     */
    WhitespacePlugin::Tab => static function (CodeBase $unused_code_base, FileCacheEntry $contents, IssueInstance $instance): ?FileEditSet {
        $spaces_per_tab = (int)(Config::getValue('plugin_config')['spaces_per_tab'] ?? 4);
        if ($spaces_per_tab <= 0) {
            $spaces_per_tab = 4;
        }

        /**
         * @return Generator<FileEdit>
         */
        $compute_edits = static function (string $line_contents, int $byte_offset) use ($spaces_per_tab): Generator {
            preg_match_all('/\t+/', $line_contents, $matches, PREG_OFFSET_CAPTURE);

            $effective_space_count = 0;
            $prev_end = 0;  // byte offset of previous end of tab sequences
            // run the equivalent of unix's 'unexpand'
            foreach ($matches[0] as $match) {
                $column = $match[1];  // 0-based column
                $effective_space_count += $column - $prev_end;
                $len = strlen($match[0]);

                $prev_end = $column + $len;

                $replacement_space_count = ($len - 1) * $spaces_per_tab + ($spaces_per_tab - ($effective_space_count % $spaces_per_tab));

                $start = $byte_offset + $match[1];
                yield new FileEdit($start, $start + $len, str_repeat(' ', $replacement_space_count));
            }
        };

        IssueFixer::debug("Calling tab fixer for {$instance->getFile()}\n");
        $raw_contents = $contents->getContents();
        $byte_offset = 0;
        $edits = [];
        foreach (explode("\n", $raw_contents) as $line_contents) {
            if (strpos($line_contents, "\t") !== false) {
                foreach ($compute_edits(rtrim($line_contents), $byte_offset) as $edit) {
                    $edits[] = $edit;
                }
            }
            $byte_offset += strlen($line_contents) + 1;
        }
        if (!$edits) {
            return null;
        }
        IssueFixer::debug("Resulting edits for tab fixes: " . json_encode($edits) . "\n");
        //$line = $instance->getLine();
        return new FileEditSet($edits);
    },
    /**
     * @return ?FileEditSet
     */
    WhitespacePlugin::WhitespaceTrailing => static function (CodeBase $unused_code_base, FileCacheEntry $contents, IssueInstance $instance): ?FileEditSet {
        IssueFixer::debug("Calling trailing whitespace fixer {$instance->getFile()}\n");
        $raw_contents = $contents->getContents();
        $byte_offset = 0;
        $edits = [];
        foreach (explode("\n", $raw_contents) as $line_contents) {
            $new_byte_offset = $byte_offset + strlen($line_contents) + 1;
            $line_contents = rtrim($line_contents, "\r");
            if (preg_match('/\s+$/', $line_contents, $matches)) {
                $len = strlen($matches[0]);
                $offset = $byte_offset + strlen($line_contents) - $len;
                // Remove 1 or more bytes of trailing whitespace from each line
                $edits[] = new FileEdit($offset, $offset + $len);
            }
            $byte_offset = $new_byte_offset;
        }
        if (!$edits) {
            return null;
        }
        IssueFixer::debug("Resulting edits for trailing whitespace: " . json_encode($edits) . "\n");
        //$line = $instance->getLine();
        return new FileEditSet($edits);
    },

    /**
     * @return ?FileEditSet
     */
    WhitespacePlugin::CarriageReturn => static function (CodeBase $unused_code_base, FileCacheEntry $contents, IssueInstance $instance): ?FileEditSet {
        IssueFixer::debug("Calling trailing whitespace fixer {$instance->getFile()}\n");
        $raw_contents = $contents->getContents();
        $byte_offset = 0;
        $edits = [];
        foreach (explode("\n", $raw_contents) as $line_contents) {
            if (substr($line_contents, -1) === "\r") {
                $offset = $byte_offset + strlen($line_contents) - 1;
                // Remove the byte with the carriage return
                $edits[] = new FileEdit($offset, $offset + 1);
            }
            $byte_offset += strlen($line_contents) + 1;
        }
        if (!$edits) {
            return null;
        }
        IssueFixer::debug("Resulting edits for trailing whitespace: " . json_encode($edits) . "\n");
        //$line = $instance->getLine();
        return new FileEditSet($edits);
    },
];
