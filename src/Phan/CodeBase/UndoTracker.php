<?php declare(strict_types=1);
namespace Phan\CodeBase;

use Phan\CodeBase;
use Phan\Daemon;

/**
 *
 * (We don't garbage collect reference cycles, so this attempts to work in a way that avoids cycles.
 *  Haven't verified that it does that as expected, yet)
 */
class UndoTracker {

    /**
     * @var string|null absolute path to currently parsed file, when in parse phase.
     * TODO: Does the Context->getFile() make keeping this redundant?
     */
    private $current_parsed_file;

    /**
     * @var \Closure[][] operations to undo for a current path
     */
    private $undoOperationsForPath = [];

    /**
     * @var string[] Maps file paths to the modification dates and file size of the paths. - On ext4, milliseconds are available, but php APIs all return seconds.
     */
    private $fileModificationState = [];

    public function __construct() {
    }

    /**
     * @return string[] - The list of files which are successfully parsed.
     * This changes whenever the file list is reloaded from disk.
     * This also includes files which don't declare classes or functions or globals,
     * because those files use classes/functions/constants.
     *
     * (This is the list prior to any analysis exclusion or whitelisting steps)
     */
    public function getParsedFilePathList() : array {
        return array_keys($this->fileModificationState);
    }

    /**
     * @return string[] - The size of $this->getParsedFilePathList()
     */
    public function getParsedFilePathCount() : int {

        return count($this->fileModificationState);
    }

    /**
     * @param string|null $current_parsed_file
     * @return void
     */
    public function setCurrentParsedFile($current_parsed_file) {
        if (is_string($current_parsed_file)) {
            Daemon::debugf("Recording file modification state for %s", $current_parsed_file);
            $this->fileModificationState[$current_parsed_file] = self::getFileState($current_parsed_file);
        }
        $this->current_parsed_file = $current_parsed_file;
    }


    /**
     * @return string|null - This string should change when the file is modified. Returns null if the file somehow doesn't exist
     */
    public static function getFileState(string $path) {
        clearstatcache(true, $path);  // TODO: does this work properly with symlinks? seems to.
        $real = realpath($path);
        if (!$real) {
            return null;
        }
        $stat = @stat($real);  // suppress notices because phan's error_handler terminates on error.
        if (!$stat) {
            return null;  // It was missing or unreadable.
        }
        return sprintf('%d_%d', $stat['mtime'], $stat['size']);
    }

    /**
     * Called when a file is unparseable.
     * Removes the classes and functions, etc. from an older version of the file, if one exists.
     * @return void
     */
    public function recordUnparseableFile(CodeBase $code_base, string $current_parsed_file) {
        Daemon::debugf("%s was unparseable, had a syntax error", $current_parsed_file);
        $this->undoFileChanges($code_base, $current_parsed_file);
        unset($this->fileModificationState[$current_parsed_file]);
    }

    /**
     * Undoes all of the changes for the relative path at $path
     * @return void
     */
    private function undoFileChanges(CodeBase $code_base, string $path) {
        Daemon::debugf("Undoing file changes for $path");
        foreach ($this->undoOperationsForPath[$path] ?? [] as $undo_operation) {
            $undo_operation($code_base);
        }
        unset($this->undoOperationsForPath[$path]);
    }

    /**
     * @param \Closure $undo_operation - a closure expecting 1 param - inner. It undoes a change caused by a parsed file.
     * Ideally, this would extend to all changes. (e.g. including dead code detection)
     *
     * @return void
     */
    public function recordUndo(\Closure $undo_operation) {
        $file = $this->current_parsed_file;
        if (!is_string($file)) {
            debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            throw new \Error("Called recordUndo in CodeBaseMutable, but not parsing a file");
        }
        if (!isset($this->undoOperationsForPath[$file])) {
            $this->undoOperationsForPath[$file] = [];
        }
        $this->undoOperationsForPath[$file][] = $undo_operation;
    }

    /**
     * @param CodeBase $code_base - code base owning this tracker
     * @param string[] $new_file_list
     * @return string[] - Subset of $new_file_list which changed on disk and has to be parsed again. Automatically unparses the old versions of files which were modified.
     */
    public function updateFileList(CodeBase $code_base, array $new_file_list) {
        $new_file_set = [];
        foreach ($new_file_list as $path) {
            $new_file_set[$path] = true;
        }
        $changed_or_added_file_list = [];
        foreach ($new_file_list as $path) {
            if (!isset($this->fileModificationState[$path])) {
                $changed_or_added_file_list[] = $path;
            }
        }
        foreach ($this->fileModificationState as $path => $state) {
            if (!isset($new_file_set[$path])) {
                $this->undoFileChanges($code_base, $path);
                unset($this->fileModificationState[$path]);
                continue;
            }
            $newState = self::getFileState($path);
            if ($newState !== $state) {
                $this->undoFileChanges($code_base, $path);
                // TODO: This will call stat() twice as much as necessary for the modified files. Not important.
                unset($this->fileModificationState[$path]);
                if ($newState !== null) {
                    $changed_or_added_file_list[] = $path;
                }
            }
        }
        return $changed_or_added_file_list;
    }
}
