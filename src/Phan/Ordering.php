<?php declare(strict_types=1);
namespace Phan;

class Ordering
{
    /** @param CodeBase */
    private $code_base;

    /**
     * @param CodeBase $code_base
     * The entire code base used to choose a file
     * analysis ordering.
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
     * @param string[] $analysis_file_list
     * A list of files that should be analyzed which will be
     * used to ignore any files outside of the list and to
     * draw from for any missing files.
     *
     * @return string[][]
     * A map from process_id to a list of files to be analyzed
     * on that process in stable ordering.
     */
    public function orderForProcessCount(
        int $process_count,
        array $analysis_file_list
    ) : array {

        assert($process_count > 0,
            "The process count must be greater than zero.");

        // Create a Set from the file list
        $analysis_file_map = [];
        foreach ($analysis_file_list as $i => $file) {
            $analysis_file_map[$file] = true;
        }

        // A map from the root of an object hierarchy to all
        // elements within that hierarchy
        $root_fqsen_list = [];

        // Iterate over each class extracting files
        foreach ($this->code_base->getClassMap() as $fqsen => $class) {

            // We won't be analyzing internal stuff
            if ($class->isInternal()) {
                continue;
            }

            // Get the name of the file associated with the class
            $file_name = $class->getContext()->getFile();

            // Ignore any files that are not to be analyzed
            if (!isset($analysis_file_map[$file_name])) {
                continue;
            }
            unset($analysis_file_map[$file_name]);

            // Get the class's depth in its object hierarchy and
            // the FQSEN of the object at the root of its hierarchy
            $hierarchy_depth = $class->getHierarchyDepth($this->code_base);
            $hierarchy_root = $class->getHierarchyRootFQSEN($this->code_base);

            // Create a bucket for this root if it doesn't exist
            if (empty($root_fqsen_list[(string)$hierarchy_root])) {
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
        $i = 1;
        foreach ($root_fqsen_list as $root_fqsen => $list) {
            usort($list, function(array $a, array $b) {
                return ($a['depth'] <=> $b['depth']);
            });

            // Choose which process this file list will be
            // run on
            $process_id = ($i++ % $process_count);

            // Append each file to this process list
            foreach ($list as $item) {
                $processor_file_list_map[$process_id][] = $item['file'];
            }

        }

        // Distribute any remaining files without classes evenly
        // between the processes
        $i = 1;
        foreach (array_keys($analysis_file_map) as $file) {
            // Choose which process this file list will be
            // run on
            $process_id = ($i++ % $process_count);

            $processor_file_list_map[$process_id][] = $file;
        }

        return $processor_file_list_map;
    }
}
