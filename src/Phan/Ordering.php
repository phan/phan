<?php

declare(strict_types=1);

namespace Phan;

use Closure;
use InvalidArgumentException;
use Phan\Language\Element\Clazz;
use Phan\Library\Hasher\Consistent;
use Phan\Library\Hasher\Sequential;

/**
 * This determines the order in which files will be analyzed.
 * Affected by `process_file_assignment`, `consistent_hashing_file_order` and `randomize_file_order`.
 * By default, files are analyzed in the same order as `.phan/config.php`
 */
class Ordering
{
    /**
     * With `process_file_assignment` set to `'balanced'` and `consistent_hashing_file_order`,
     * a group of files is assigned to the first process on the hash ring whose load stays within
     * this many percent above an even share of the total weight.
     */
    private const BOUNDED_LOAD_TOLERANCE_PERCENT = 15;

    /**
     * @var CodeBase
     * The entire code base. Used to choose a file analysis ordering.
     */
    private $code_base;

    /**
     * @var ?Closure(string):int
     * Returns the size of a file in bytes (for tests). Defaults to the size on disk.
     */
    private $file_size_callback;

    /**
     * @param CodeBase $code_base
     * The entire code base. Used to choose a file analysis ordering.
     *
     * @param ?Closure(string):int $file_size_callback
     * Returns the size in bytes of a file in the analyzed file list,
     * used to balance the work of processes with `process_file_assignment` set to `'balanced'`.
     * Defaults to the size of the file on disk.
     */
    public function __construct(CodeBase $code_base, ?Closure $file_size_callback = null)
    {
        $this->code_base = $code_base;
        $this->file_size_callback = $file_size_callback;
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

        if ($process_count > 1 && Config::getValue('process_file_assignment') === 'balanced') {
            return $this->orderBalancedForProcessCount($process_count, $analysis_file_list);
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

    /**
     * Implements `process_file_assignment` = `'balanced'` for more than one process.
     *
     * Files are grouped by the topmost ancestor (of the first class declared in the file) whose file is analyzed,
     * and weighted by their size. Groups weighing more than an even share are split into the subtrees of the child
     * classes of their root class (recursively). Groups are assigned heaviest first, either to the least loaded process
     * or (with `consistent_hashing_file_order`) with consistent hashing with bounded loads.
     * Each process analyzes its files in the order that the default assignment would use with a single process.
     *
     * The result only depends on the file list, the class hierarchy, the file sizes and the process count.
     *
     * @param list<string> $analysis_file_list
     * @return associative-array<int,list<string>>
     * A map from process_id to a list of files to be analyzed on that process.
     */
    private function orderBalancedForProcessCount(int $process_count, array $analysis_file_list): array
    {
        $use_consistent_hashing = (bool)Config::getValue('consistent_hashing_file_order');
        if ($use_consistent_hashing) {
            \sort($analysis_file_list, \SORT_STRING);
        }

        $analysis_file_set = [];
        foreach ($analysis_file_list as $file) {
            $analysis_file_set[$file] = true;
        }

        // Use the same class for each file as the default assignment (the first class declared in it).
        $class_less_file_set = $analysis_file_set;
        $class_for_file = [];
        foreach ($this->code_base->getUserDefinedClassMap() as $class) {
            $file = $class->getContext()->getFile();
            if (!isset($class_less_file_set[$file])) {
                continue;
            }
            unset($class_less_file_set[$file]);
            $class_for_file[$file] = $class;
        }
        if ($use_consistent_hashing) {
            \ksort($class_for_file, \SORT_STRING);
        }

        // Files grouped by the root of their class hierarchy, as in the default assignment (used for the order within a process).
        $files_for_root = [];
        // Files grouped by the topmost ancestor class declared in an analyzed file,
        // along with the FQSENs of the classes on the path from that ancestor down to the file's class (used for splitting).
        $items_for_bucket = [];
        foreach ($class_for_file as $file => $class) {
            $file = (string)$file;
            $hierarchy_depth = $class->getHierarchyDepth($this->code_base);
            $chain = $this->getHierarchyChain($class);
            $files_for_root[$chain[\count($chain) - 1]->getFQSEN()->__toString()][] = [
                'file' => $file,
                'depth' => $hierarchy_depth,
            ];

            $top = 0;
            foreach ($chain as $i => $ancestor) {
                if (isset($analysis_file_set[$ancestor->getContext()->getFile()])) {
                    $top = $i;
                }
            }
            $path = [];
            for ($i = $top - 1; $i >= 0; $i--) {
                $path[] = $chain[$i]->getFQSEN()->__toString();
            }
            $items_for_bucket[$chain[$top]->getFQSEN()->__toString()][] = [$file, $path];
        }

        // The position of each file in the order used by the default assignment for a single process:
        // hierarchies in order of first appearance, files sorted by depth and name, then files without classes.
        $rank = [];
        foreach ($files_for_root as $list) {
            \usort(
                $list,
                /**
                 * @param array{depth:int,file:string} $a
                 * @param array{depth:int,file:string} $b
                 */
                static function (array $a, array $b): int {
                    return ($a['depth'] <=> $b['depth']) ?:
                           \strcmp($a['file'], $b['file']);
                }
            );
            foreach ($list as $item) {
                $rank[$item['file']] = \count($rank);
            }
        }
        foreach ($class_less_file_set as $file => $_) {
            $rank[(string)$file] = \count($rank);
        }

        $weight = [];
        $total_weight = 0;
        foreach ($analysis_file_set as $file => $_) {
            $file = (string)$file;
            $file_weight = $this->getFileSize($file);
            if ($file_weight < 1) {
                $file_weight = 1;
            }
            $weight[$file] = $file_weight;
            $total_weight += $file_weight;
        }
        $cap = \intdiv($total_weight + $process_count - 1, $process_count);

        /** @var list<array{0:string,1:int,2:list<string>}> $groups (key, weight, files) */
        $groups = [];
        \ksort($items_for_bucket, \SORT_STRING);
        foreach ($items_for_bucket as $root_fqsen => $items) {
            foreach (self::splitGroup((string)$root_fqsen, $items, 0, $weight, $cap) as $group) {
                $groups[] = $group;
            }
        }
        foreach ($class_less_file_set as $file => $_) {
            $file = (string)$file;
            $groups[] = [$file, $weight[$file], [$file]];
        }
        \usort(
            $groups,
            /**
             * Heaviest first, ties broken by key.
             * @param array{0:string,1:int,2:list<string>} $a
             * @param array{0:string,1:int,2:list<string>} $b
             */
            static function (array $a, array $b): int {
                return ($b[1] <=> $a[1]) ?: \strcmp($a[0], $b[0]);
            }
        );

        $hasher = $use_consistent_hashing ? new Consistent($process_count) : null;
        $loads = \array_fill(0, $process_count, 0);
        $files_for_process = \array_fill(0, $process_count, []);
        foreach ($groups as [$key, $group_weight, $files]) {
            $process_id = $hasher ? self::chooseProcessWithBoundedLoad($hasher, $key, $group_weight, $loads, $total_weight) : self::getLeastLoadedProcess($loads);
            $loads[$process_id] += $group_weight;
            foreach ($files as $file) {
                $files_for_process[$process_id][] = $file;
            }
        }

        $processor_file_list_map = [];
        foreach ($files_for_process as $process_id => $files) {
            if (!$files) {
                continue;
            }
            \usort(
                $files,
                static function (string $a, string $b) use ($rank): int {
                    return $rank[$a] <=> $rank[$b];
                }
            );
            $processor_file_list_map[$process_id] = $files;
        }
        return $processor_file_list_map;
    }

    /**
     * @return non-empty-list<Clazz> $class followed by its ancestor classes,
     * ending with the class whose FQSEN getHierarchyRootFQSEN() returns.
     */
    private function getHierarchyChain(Clazz $class): array
    {
        $chain = [$class];
        $visited = [];
        for ($current = $class; $current->hasParentType(); $current = $parent) {
            if (!$this->code_base->hasClassWithFQSEN($current->getParentClassFQSEN())) {
                break;
            }
            $parent = $current->getParentClass($this->code_base);
            $visited[$current->getFQSEN()->__toString()] = true;
            // Prevent infinite loops
            if (\array_key_exists($parent->getFQSEN()->__toString(), $visited)) {
                break;
            }
            $chain[] = $parent;
        }
        return $chain;
    }

    private function getFileSize(string $file): int
    {
        if ($this->file_size_callback) {
            return ($this->file_size_callback)($file);
        }
        $path = Config::projectPath($file);
        $size = \is_file($path) ? \filesize($path) : false;
        return \is_int($size) ? $size : 0;
    }

    /**
     * Splits a group of files whose weight exceeds $cap into the subtrees of the child classes of its root class, recursively.
     * Files of the root class itself join the heaviest resulting group.
     *
     * @param string $key the FQSEN of the root class of this group
     * @param non-empty-list<array{0:string,1:list<string>}> $items
     * the files of this group, each with the FQSENs of the classes on the path from the bucket's root class to the file's class
     * @param int $level the depth of $key below the bucket's root class
     * @param array<string,int> $weight the weight of each file
     * @param int $cap groups weighing more than this are split if possible
     * @return non-empty-list<array{0:string,1:int,2:list<string>}> the groups (key, weight, files)
     */
    private static function splitGroup(string $key, array $items, int $level, array $weight, int $cap): array
    {
        $total = 0;
        $own_files = [];
        $items_for_child = [];
        $files = [];
        foreach ($items as $item) {
            [$file, $path] = $item;
            $files[] = $file;
            $total += $weight[$file];
            if (isset($path[$level])) {
                $items_for_child[$path[$level]][] = $item;
            } else {
                $own_files[] = $file;
            }
        }
        if ($total <= $cap || !$items_for_child) {
            return [[$key, $total, $files]];
        }
        \ksort($items_for_child, \SORT_STRING);
        $groups = [];
        foreach ($items_for_child as $child_fqsen => $child_items) {
            foreach (self::splitGroup((string)$child_fqsen, $child_items, $level + 1, $weight, $cap) as $group) {
                $groups[] = $group;
            }
        }
        if ($own_files) {
            $heaviest = 0;
            foreach ($groups as $i => $group) {
                if ($group[1] > $groups[$heaviest][1]) {
                    $heaviest = $i;
                }
            }
            [$heaviest_key, $heaviest_weight, $heaviest_files] = $groups[$heaviest];
            foreach ($own_files as $file) {
                $heaviest_weight += $weight[$file];
                $heaviest_files[] = $file;
            }
            $groups[$heaviest] = [$heaviest_key, $heaviest_weight, $heaviest_files];
        }
        return $groups;
    }

    /**
     * Consistent hashing with bounded loads: walk the hash ring from the position of $key
     * and choose the first process that stays within the tolerance above an even share of $total_weight,
     * or the least loaded process if none does.
     *
     * @param list<int> $loads the weight assigned to each process so far
     */
    private static function chooseProcessWithBoundedLoad(Consistent $hasher, string $key, int $weight, array $loads, int $total_weight): int
    {
        $process_count = \count($loads);
        foreach ($hasher->getGroupsInRingOrder($key) as $process_id) {
            // ($loads[$process_id] + $weight) <= (1 + tolerance) * $total_weight / $process_count, in integer arithmetic
            if (($loads[$process_id] + $weight) * $process_count * 100 <= (100 + self::BOUNDED_LOAD_TOLERANCE_PERCENT) * $total_weight) {
                return $process_id;
            }
        }
        return self::getLeastLoadedProcess($loads);
    }

    /**
     * @param list<int> $loads the weight assigned to each process so far
     * @return int the least loaded process (the lowest index among ties)
     */
    private static function getLeastLoadedProcess(array $loads): int
    {
        $best = 0;
        foreach ($loads as $process_id => $load) {
            if ($load < $loads[$best]) {
                $best = $process_id;
            }
        }
        return $best;
    }
}
