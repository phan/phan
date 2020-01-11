<?php

declare(strict_types=1);

namespace Phan;

use InvalidArgumentException;
use Phan\Library\Hasher\Consistent;
use Phan\Library\Hasher\Sequential;

/**
 * This determines the order in which files will be analyzed.
 * Affected by `consistent_hashing_file_order` and `randomize_file_order`.
 * By default, files are analyzed in the same order as `.phan/config.php`
 */
class Ordering
{
    /**
     * @var CodeBase
     * The entire code base. Used to choose a file analysis ordering.
     */
    private $code_base;

    /**
     * @param CodeBase $code_base
     * The entire code base. Used to choose a file analysis ordering.
     */
    public function __construct(CodeBase $code_base)
    {
        $this->code_base = $code_base;
    }

    /**
     * @param int $process_count
     * The number of processes we'd like to divide work up
     * amongst.
     *
     * @param list<string> $analysis_file_list
     * A list of files that should be analyzed which will be
     * used to ignore any files outside of the list and to
     * draw from for any missing files.
     *
     * @return associative-array<int,list<string>>
     * A map from process_id to a list of files to be analyzed
     * on that process in stable ordering.
     * @throws InvalidArgumentException if $process_count isn't positive.
     */
    public function orderForProcessCount(
        int $process_count,
        array $analysis_file_list
    ): array {

        if ($process_count <= 0) {
            throw new InvalidArgumentException("The process count must be greater than zero.");
        }

        if (Config::getValue('randomize_file_order')) {
            $random_proc_file_map = [];
            \shuffle($analysis_file_list);
            foreach ($analysis_file_list as $i => $file) {
                $random_proc_file_map[$i % $process_count][] = $file;
            }
            return $random_proc_file_map;
        }

        // Construct a Hasher implementation based on config.
        if (Config::getValue('consistent_hashing_file_order')) {
            \sort($analysis_file_list, \SORT_STRING);
            $hasher = new Consistent($process_count);
        } else {
            $hasher = new Sequential($process_count);
        }

        // Create a Set from the file list
        $analysis_file_map = [];
        foreach ($analysis_file_list as $file) {
            $analysis_file_map[$file] = true;
        }

        // A map from the root of an object hierarchy to all
        // elements within that hierarchy
        $root_fqsen_list = [];

        $file_names_for_classes = [];

        // Iterate over each class extracting files
        foreach ($this->code_base->getUserDefinedClassMap() as $class) {
            // Get the name of the file associated with the class
            $file_name = $class->getContext()->getFile();

            // Ignore any files that are not to be analyzed
            if (!isset($analysis_file_map[$file_name])) {
                continue;
            }
            unset($analysis_file_map[$file_name]);
            $file_names_for_classes[$file_name] = $class;
        }

        if (Config::getValue('consistent_hashing_file_order')) {
            \ksort($file_names_for_classes, \SORT_STRING);
        }

        foreach ($file_names_for_classes as $file_name => $class) {
            // Get the class's depth in its object hierarchy and
            // the FQSEN of the object at the root of its hierarchy
            $hierarchy_depth = $class->getHierarchyDepth($this->code_base);
            $hierarchy_root = $class->getHierarchyRootFQSEN($this->code_base);

            // Create a bucket for this root if it doesn't exist
            if (!isset($root_fqsen_list[(string)$hierarchy_root])) {
                $root_fqsen_list[(string)$hierarchy_root] = [];
            }

            // Append this {file,depth} pair to the hierarchy
            // root
            $root_fqsen_list[(string)$hierarchy_root][] = [
                'file'  => $file_name,
                'depth' => $hierarchy_depth,
            ];
        }

        // Create a map from processor_id to the list of files
        // to be analyzed on that processor
        $processor_file_list_map = [];

        // Sort the set of files with a given root by their
        // depth in the hierarchy
        foreach ($root_fqsen_list as $root_fqsen => $list) {
            \usort(
                $list,
                /**
                 * Sort first by depth, and break ties by file name lexicographically
                 * (usort is not a stable sort).
                 * @param array{depth:int,file:string} $a
                 * @param array{depth:int,file:string} $b
                 */
                static function (array $a, array $b): int {
                    return ($a['depth'] <=> $b['depth']) ?:
                           \strcmp($a['file'], $b['file']);
                }
            );

            // Choose which process this file list will be
            // run on
            $process_id = $hasher->getGroup((string)$root_fqsen);

            // Append each file to this process list
            foreach ($list as $item) {
                $processor_file_list_map[$process_id][] = (string)$item['file'];
            }
        }

        // Distribute any remaining files without classes evenly
        // between the processes
        $hasher->reset();
        foreach (\array_keys($analysis_file_map) as $file) {
            // Choose which process this file list will be
            // run on
            $file = (string)$file;
            $process_id = $hasher->getGroup($file);

            $processor_file_list_map[$process_id][] = $file;
        }

        return $processor_file_list_map;
    }
}
